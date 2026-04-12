<?php

namespace App\Console\Commands;

use App\Models\Folder;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

#[Signature('documents:import-tasbibat {source : Absolute path to the "التسبيبات القضائية" folder} {--user=1 : Uploader user id} {--reindex : Reindex Elasticsearch after import}')]
#[Description('Bulk-import judicial reasoning PDFs categorized by court specialty into LegalDocument + folders')]
class ImportTasbibat extends Command
{
    /**
     * Folder name → specialty metadata.
     * Maps each court specialty to its display label, metadata key,
     * and a folder name used for grouping on the documents index.
     */
    private const SPECIALTIES = [
        'التسبيبات الشاملة لعدة اختصاصات' => [
            'key'     => 'general_mixed',
            'label'   => 'تسبيبات شاملة لعدة اختصاصات',
            'entity'  => 'قضاء عام (شامل)',
            'folder'  => 'تسبيبات شاملة لعدة اختصاصات',
        ],
        'تسبيبات القضاء التجاري' => [
            'key'     => 'commercial',
            'label'   => 'القضاء التجاري',
            'entity'  => 'المحكمة التجارية',
            'folder'  => 'تسبيبات القضاء التجاري',
        ],
        'تسبيبات القضاء الجزائي' => [
            'key'     => 'criminal',
            'label'   => 'القضاء الجزائي',
            'entity'  => 'المحكمة الجزائية',
            'folder'  => 'تسبيبات القضاء الجزائي',
        ],
        'تسبيبات القضاء العمالي' => [
            'key'     => 'labor',
            'label'   => 'القضاء العمالي',
            'entity'  => 'المحكمة العمالية',
            'folder'  => 'تسبيبات القضاء العمالي',
        ],
        'تسبيبات قضاء الأحوال الشخصية' => [
            'key'     => 'personal_status',
            'label'   => 'قضاء الأحوال الشخصية',
            'entity'  => 'محكمة الأحوال الشخصية',
            'folder'  => 'تسبيبات قضاء الأحوال الشخصية',
        ],
        'تسبيبات قضاء التنفيذ' => [
            'key'     => 'enforcement',
            'label'   => 'قضاء التنفيذ',
            'entity'  => 'محكمة التنفيذ',
            'folder'  => 'تسبيبات قضاء التنفيذ',
        ],
    ];

    /**
     * Loose-filename → specialty for root-level loose PDFs.
     */
    private const ROOT_FILE_MAP = [
        'التسبيبات_التجارية'   => 'تسبيبات القضاء التجاري',
        'تسبيبات القضاء العام' => 'التسبيبات الشاملة لعدة اختصاصات',
        'تسبيبات_في_التنفيذ'   => 'تسبيبات قضاء التنفيذ',
    ];

    public function handle(TextExtractorService $extractor, ElasticsearchService $es): int
    {
        $source = $this->argument('source');
        if (! is_dir($source)) {
            $this->error("المصدر غير موجود: {$source}");
            return self::FAILURE;
        }

        $user = User::find((int) $this->option('user'));
        if (! $user) {
            $this->error('المستخدم غير موجود.');
            return self::FAILURE;
        }

        $this->info("📥 استيراد التسبيبات من: {$source}");
        $this->line("   المستخدم: {$user->email} (org #{$user->org_id})");

        $stats = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'text_ok' => 0, 'text_empty' => 0];

        // ─── Pass 1: loose PDFs at the root ───
        foreach (scandir($source) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $source . DIRECTORY_SEPARATOR . $entry;
            if (! is_file($path)) continue;
            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'pdf') continue;

            $specialtyDir = $this->resolveRootFileSpecialty($entry);
            if ($specialtyDir === null) {
                $this->warn("⚠  ملف جذري غير مصنّف — يُتخطى: {$entry}");
                $stats['skipped']++;
                continue;
            }
            $this->importFile($path, $entry, self::SPECIALTIES[$specialtyDir], $user, $extractor, $stats);
        }

        // ─── Pass 2: each specialty folder ───
        foreach (self::SPECIALTIES as $dirName => $spec) {
            $dir = $source . DIRECTORY_SEPARATOR . $dirName;
            if (! is_dir($dir)) {
                $this->warn("⚠  مجلد مفقود: {$dirName}");
                continue;
            }
            $this->line("\n📁 {$spec['label']}");
            $files = scandir($dir) ?: [];
            // Prefer PDF over DOCX when both exist for the same base name.
            $pdfBasenames = [];
            foreach ($files as $f) {
                if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'pdf') {
                    $pdfBasenames[pathinfo($f, PATHINFO_FILENAME)] = true;
                }
            }

            foreach ($files as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (! is_file($path)) continue;
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (! in_array($ext, ['pdf', 'docx', 'doc', 'txt'], true)) continue;

                // If a DOCX/DOC twin of a PDF exists, skip the non-PDF to avoid duplicates.
                if ($ext !== 'pdf') {
                    $base = pathinfo($entry, PATHINFO_FILENAME);
                    if (isset($pdfBasenames[$base])) {
                        $stats['skipped']++;
                        continue;
                    }
                }

                $this->importFile($path, $entry, $spec, $user, $extractor, $stats);
            }
        }

        // ─── Summary ───
        $this->newLine();
        $this->info('══════════════════════════════════');
        $this->info("✓ تم إنشاء: {$stats['created']}");
        $this->line("⊘ تم تخطي: {$stats['skipped']}");
        $this->line("✗ فشل: {$stats['failed']}");
        $this->line("📝 استخراج نص ناجح: {$stats['text_ok']} — فارغ: {$stats['text_empty']}");

        if ($this->option('reindex') && $es->isAvailable() && $stats['created'] > 0) {
            $this->newLine();
            $this->info('🔄 إعادة فهرسة Elasticsearch...');
            $this->call('documents:reindex');
        }

        return self::SUCCESS;
    }

    private function resolveRootFileSpecialty(string $filename): ?string
    {
        foreach (self::ROOT_FILE_MAP as $needle => $specialtyDir) {
            if (mb_stripos($filename, $needle) !== false) {
                return $specialtyDir;
            }
        }
        return null;
    }

    private function importFile(
        string $absolutePath,
        string $originalName,
        array $spec,
        User $user,
        TextExtractorService $extractor,
        array &$stats,
    ): void {
        try {
            $title = $this->titleFromFilename($originalName);

            // Skip if a document with the same title + org already exists (idempotent reruns)
            $exists = LegalDocument::where('org_id', $user->org_id)
                ->where('title', $title)
                ->where('kind', LegalDocument::KIND_DOCUMENT)
                ->exists();
            if ($exists) {
                $this->line("  ⊘ موجود مسبقاً: {$title}");
                $stats['skipped']++;
                return;
            }

            // Copy file into public storage under documents/
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $storedName = 'documents/tasbibat_' . uniqid() . '.' . $ext;
            $fileStream = fopen($absolutePath, 'rb');
            Storage::disk('public')->put($storedName, $fileStream);
            if (is_resource($fileStream)) fclose($fileStream);

            // Extract text (graceful — some scanned PDFs may yield empty)
            $content = $extractor->extract(Storage::disk('public')->path($storedName));
            if ($content !== null && $content !== '') {
                $stats['text_ok']++;
            } else {
                $stats['text_empty']++;
            }

            DB::transaction(function () use ($storedName, $originalName, $absolutePath, $title, $content, $spec, $user) {
                $doc = LegalDocument::create([
                    'org_id'        => $user->org_id,
                    'title'         => $title,
                    'type'          => 6, // حكم قضائي
                    'kind'          => LegalDocument::KIND_DOCUMENT,
                    'summary'       => "تسبيبات قضائية — {$spec['label']}",
                    'content'       => $content ?: '',
                    'source_entity' => $spec['entity'],
                    'file_path'     => $storedName,
                    'file_name'     => $originalName,
                    'file_size'     => filesize($absolutePath) ?: 0,
                    'uploaded_by'   => $user->id,
                    'is_private'    => false,
                    'metadata'      => [
                        'specialty'         => $spec['key'],
                        'specialty_label'   => $spec['label'],
                        'import_batch'      => 'tasbibat_2026_04',
                        'extraction_status' => $content ? 'ready' : 'empty',
                    ],
                ]);

                // Attach to specialty folder (create-or-find)
                $folder = Folder::firstOrCreate(
                    ['org_id' => $user->org_id, 'name' => $spec['folder']],
                    ['owner_id' => $user->id, 'description' => 'تسبيبات قضائية — ' . $spec['label']],
                );
                $folder->documents()->syncWithoutDetaching([
                    $doc->id => ['added_by' => $user->id],
                ]);
            });

            $this->line("  ✓ {$title}");
            $stats['created']++;
        } catch (\Throwable $e) {
            $stats['failed']++;
            $this->error("  ✗ فشل {$originalName}: " . $e->getMessage());
        }
    }

    private function titleFromFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        // Strip leading decorative markers like 🔴__ and trailing duplicates
        $base = preg_replace('/^[\p{So}\p{Sk}_\s]+/u', '', $base) ?? $base;
        $base = preg_replace('/[\p{So}\p{Sk}_\s]+$/u', '', $base) ?? $base;
        $base = str_replace('_', ' ', $base);
        return trim(preg_replace('/\s+/u', ' ', $base) ?? $base);
    }
}
