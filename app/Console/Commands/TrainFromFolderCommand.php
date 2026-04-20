<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use App\Models\Organization;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Walks a folder recursively, extracts text from every supported file
 * (PDF / DOCX / XLS / TXT), and stores each one as a LegalDocument under a
 * dedicated "training library" organisation. Files are categorised by their
 * parent folder name so the RAG layer can later retrieve the right corpus
 * (systems, tenders, memos, rulings, contract templates, ...).
 *
 * Usage:
 *   php artisan mizaan:train-from-folder "C:/path/to/folder"
 *   php artisan mizaan:train-from-folder "..." --dry-run --limit=20
 */
class TrainFromFolderCommand extends Command
{
    protected $signature = 'mizaan:train-from-folder
        {path : Root folder to scan}
        {--org= : Organization id (default: training library)}
        {--limit=0 : Max files to import (0 = no limit)}
        {--dry-run : Scan and print what would import; do not persist}
        {--skip-existing : Skip files already imported (by name+size)}';

    protected $description = 'Import a folder of PDFs/DOCX/XLS as reference corpus for the AI knowledge base';

    private const SUPPORTED_EXT = ['pdf', 'docx', 'xls', 'xlsx', 'txt'];

    /** Folder name → (kind, document type) map. First match wins. */
    private const CATEGORY_MAP = [
        'التسبي'        => ['kind' => 'case',            'type' => 6, 'tag' => 'تسبيبات قضائية'],
        'تسبيب'         => ['kind' => 'case',            'type' => 6, 'tag' => 'تسبيبات قضائية'],
        'تسبيبات'       => ['kind' => 'case',            'type' => 6, 'tag' => 'تسبيبات قضائية'],
        'الأنظمة'       => ['kind' => 'document',        'type' => 1, 'tag' => 'أنظمة ولوائح'],
        'الانظمة'       => ['kind' => 'document',        'type' => 1, 'tag' => 'أنظمة ولوائح'],
        'الكراسات'      => ['kind' => 'tender_review',   'type' => 1, 'tag' => 'كراسات مرجعية'],
        'كراسات'        => ['kind' => 'tender_review',   'type' => 1, 'tag' => 'كراسات مرجعية'],
        'مذكرات'        => ['kind' => 'memo',            'type' => 1, 'tag' => 'مذكرات مرجعية'],
        'القضاء'        => ['kind' => 'case',            'type' => 6, 'tag' => 'أحكام قضائية'],
        'قضاء'          => ['kind' => 'case',            'type' => 6, 'tag' => 'أحكام قضائية'],
        'عقد'           => ['kind' => 'contract',        'type' => 1, 'tag' => 'نماذج عقود'],
        'اتفاقية'       => ['kind' => 'contract',        'type' => 1, 'tag' => 'نماذج اتفاقيات'],
        'التأهيل'       => ['kind' => 'document',        'type' => 1, 'tag' => 'وثائق تأهيل'],
        'الضريب'        => ['kind' => 'document',        'type' => 1, 'tag' => 'اتفاقيات ضريبية وجمركية'],
        'الضرائب'       => ['kind' => 'document',        'type' => 1, 'tag' => 'اتفاقيات ضريبية وجمركية'],
        'الضربي'        => ['kind' => 'document',        'type' => 1, 'tag' => 'اتفاقيات ضريبية وجمركية'],
        'الجمارك'       => ['kind' => 'document',        'type' => 1, 'tag' => 'أنظمة جمركية'],
        'جمركي'         => ['kind' => 'document',        'type' => 1, 'tag' => 'أنظمة جمركية'],

        // Ministry of Justice (MoJ) — Saudi legal reference library.
        // Order matters: file-pattern needles checked after folder-based ones,
        // so MoJ patterns only fire when no higher-priority category matched.
        'السوابق'       => ['kind' => 'case',            'type' => 6, 'tag' => 'سوابق قضائية'],
        'ديوان المظالم' => ['kind' => 'case',            'type' => 6, 'tag' => 'أحكام ديوان المظالم'],
        'مسالك تسبيب'   => ['kind' => 'case',            'type' => 6, 'tag' => 'مسالك تسبيب الأحكام'],
        'اللائحة'       => ['kind' => 'document',        'type' => 1, 'tag' => 'لوائح تنفيذية'],
        'اللوائح'       => ['kind' => 'document',        'type' => 1, 'tag' => 'لوائح تنفيذية'],
        'لائحة'         => ['kind' => 'document',        'type' => 1, 'tag' => 'لوائح'],
        'الأدلة'        => ['kind' => 'document',        'type' => 1, 'tag' => 'أدلة إجرائية'],
        'الدليل'        => ['kind' => 'document',        'type' => 1, 'tag' => 'أدلة إجرائية'],
        'القواعد'       => ['kind' => 'document',        'type' => 1, 'tag' => 'قواعد وإجراءات'],
        'قواعد'         => ['kind' => 'document',        'type' => 1, 'tag' => 'قواعد وإجراءات'],
        'ضوابط'         => ['kind' => 'document',        'type' => 1, 'tag' => 'ضوابط تنظيمية'],
        'تنظيم '        => ['kind' => 'document',        'type' => 1, 'tag' => 'تنظيمات'],
        'نظام '         => ['kind' => 'document',        'type' => 1, 'tag' => 'أنظمة'],
        'آلية'          => ['kind' => 'document',        'type' => 1, 'tag' => 'آليات تنفيذية'],
        'معجم'          => ['kind' => 'document',        'type' => 1, 'tag' => 'معاجم قانونية'],
        'العدل'         => ['kind' => 'document',        'type' => 1, 'tag' => 'مراجع عدلية'],
    ];

    public function handle(TextExtractorService $extractor, ElasticsearchService $elasticsearch): int
    {
        $root = $this->argument('path');
        if (! is_dir($root)) {
            $this->error("Folder not found: {$root}");
            return self::FAILURE;
        }

        $org = $this->resolveOrganization();
        $this->info("Target organization: {$org->name_ar} (id={$org->id})");

        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');

        $files = $this->collectFiles($root);
        $total = $files->count();
        $this->info("Found {$total} supported files. Starting " . ($dryRun ? 'DRY RUN' : 'import') . ' ...');

        $stats = [
            'imported' => 0, 'skipped_empty' => 0, 'skipped_existing' => 0,
            'skipped_error' => 0, 'skipped_by_kind' => [],
        ];
        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);

        $processed = 0;
        foreach ($files as $file) {
            if ($limit > 0 && $processed >= $limit) break;
            $processed++;

            $absolute = $file->getRealPath();
            if (! $absolute) continue;

            $category = $this->categorize($absolute, $root);

            if ($skipExisting && $this->alreadyImported($org->id, $file, $category)) {
                $stats['skipped_existing']++;
                $bar->advance();
                continue;
            }

            try {
                $text = $extractor->extract($absolute);
            } catch (Throwable $e) {
                $stats['skipped_error']++;
                $bar->advance();
                continue;
            }

            if ($text === null || mb_strlen(trim($text)) < 80) {
                $stats['skipped_empty']++;
                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $stats['imported']++;
                $stats['skipped_by_kind'][$category['kind']] = ($stats['skipped_by_kind'][$category['kind']] ?? 0) + 1;
                $bar->advance();
                continue;
            }

            try {
                $doc = LegalDocument::create([
                    'org_id'      => $org->id,
                    'uploaded_by' => null,
                    'title'       => $this->deriveTitle($file),
                    'type'        => $category['type'],
                    'kind'        => $category['kind'],
                    'content'     => mb_substr($text, 0, 500000),
                    'file_path'   => null,
                    'file_name'   => $file->getFilename(),
                    'file_size'   => $file->getSize(),
                    'is_private'  => false,
                    'metadata'    => [
                        'training_source' => true,
                        'source_folder'   => $this->relativePath($absolute, $root),
                        'imported_at'     => now()->toIso8601String(),
                        'category_tag'    => $category['tag'],
                    ],
                ]);

                try {
                    $elasticsearch->reindexDocument($doc);
                } catch (Throwable) {
                    // ES may be offline during bulk load — skip silently, chunks will still be queryable via DB search.
                }

                $stats['imported']++;
                $stats['skipped_by_kind'][$category['kind']] = ($stats['skipped_by_kind'][$category['kind']] ?? 0) + 1;
            } catch (Throwable $e) {
                $stats['skipped_error']++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Done.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Imported',         $stats['imported']],
                ['Empty / unreadable', $stats['skipped_empty']],
                ['Already imported', $stats['skipped_existing']],
                ['Extraction error', $stats['skipped_error']],
            ],
        );
        if (! empty($stats['skipped_by_kind'])) {
            $this->line('By kind:');
            foreach ($stats['skipped_by_kind'] as $kind => $count) {
                $this->line("  · {$kind}: {$count}");
            }
        }

        return self::SUCCESS;
    }

    private function resolveOrganization(): Organization
    {
        if ($orgId = $this->option('org')) {
            return Organization::findOrFail($orgId);
        }

        return Organization::firstOrCreate(
            ['domain' => 'training.library'],
            ['name_ar' => 'المكتبة المرجعية', 'name_en' => 'Training Library'],
        );
    }

    private function collectFiles(string $root): \Illuminate\Support\Collection
    {
        $finder = (new Finder())
            ->files()
            ->in($root)
            ->name($this->supportedPattern())
            ->ignoreUnreadableDirs()
            ->ignoreDotFiles(true);

        $items = [];
        foreach ($finder as $file) {
            $items[] = $file;
        }

        return collect($items);
    }

    /** @return array{kind:string,type:int,tag:string} */
    private function categorize(string $absolutePath, string $root): array
    {
        // Include the root folder basename in the search haystack — otherwise
        // files at the root of a category-named folder (e.g. "التسبيات/foo.pdf")
        // would lose their category context once the root prefix is stripped.
        $rootName = basename(rtrim(str_replace('\\', '/', $root), '/'));
        $haystack = $rootName . '/' . $this->relativePath($absolutePath, $root);

        foreach (self::CATEGORY_MAP as $needle => $meta) {
            if (mb_stripos($haystack, $needle) !== false) {
                return $meta;
            }
        }

        // Fallback: unknown folder layout → classify as generic document
        return ['kind' => 'document', 'type' => 1, 'tag' => 'مرجعي'];
    }

    private function relativePath(string $absolute, string $root): string
    {
        $absolute = str_replace('\\', '/', $absolute);
        $root = rtrim(str_replace('\\', '/', $root), '/');

        return ltrim(Str::after($absolute, $root), '/');
    }

    private function deriveTitle(SplFileInfo $file): string
    {
        $name = $file->getBasename('.' . $file->getExtension());
        // Clean common noise tokens used when files come from OneDrive exports
        $name = trim(preg_replace('/__+/u', ' ', $name) ?? $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        return mb_substr($name, 0, 250) ?: 'مستند مرجعي';
    }

    private function alreadyImported(int $orgId, SplFileInfo $file, array $category): bool
    {
        return LegalDocument::query()
            ->where('org_id', $orgId)
            ->where('file_name', $file->getFilename())
            ->where('file_size', $file->getSize())
            ->exists();
    }

    private function supportedPattern(): string
    {
        // Build a glob-style alternation (e.g. "*.{pdf,docx,xls,xlsx,txt}")
        return '*.{' . implode(',', self::SUPPORTED_EXT) . '}';
    }
}
