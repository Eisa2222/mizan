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

#[Signature('documents:import-batch {source : Absolute path to the root folder} {--user=1 : Uploader user id} {--batch= : Batch tag (defaults to folder name)} {--type=6 : Document type (1-7)} {--reindex : Reindex Elasticsearch after}')]
#[Description('Bulk-import legal documents from a folder tree into LegalDocument + auto-create folders per subfolder')]
class ImportLegalBatch extends Command
{
    public function handle(TextExtractorService $extractor, ElasticsearchService $es): int
    {
        $source = rtrim($this->argument('source'), '/\\');
        if (! is_dir($source)) {
            $this->error("المصدر غير موجود: {$source}");
            return self::FAILURE;
        }

        $user = User::find((int) $this->option('user'));
        if (! $user) {
            $this->error('المستخدم غير موجود.');
            return self::FAILURE;
        }

        $rootName = basename($source);
        $batch = $this->option('batch') ?: mb_strtolower(preg_replace('/\s+/u', '_', $rootName)) . '_' . date('Y_m');
        $type = (int) $this->option('type');

        $this->info("📥 استيراد من: {$source}");
        $this->line("   الدُفعة: {$batch} · النوع: {$type} · المستخدم: {$user->email}");

        $stats = ['created' => 0, 'skipped' => 0, 'failed' => 0, 'text_ok' => 0, 'text_empty' => 0];

        $this->importDirectory($source, $rootName, $batch, $type, $user, $extractor, $stats);

        $this->newLine();
        $this->info('══════════════════════════════════');
        $this->info("✓ تم إنشاء: {$stats['created']}");
        $this->line("⊘ تم تخطي: {$stats['skipped']}");
        $this->line("✗ فشل: {$stats['failed']}");
        $this->line("📝 نص ناجح: {$stats['text_ok']} — فارغ: {$stats['text_empty']}");

        if ($this->option('reindex') && $es->isAvailable() && $stats['created'] > 0) {
            $this->newLine();
            $this->info('🔄 إعادة فهرسة Elasticsearch...');
            $this->call('documents:reindex');
        }

        return self::SUCCESS;
    }

    private function importDirectory(
        string $dir,
        string $folderName,
        string $batch,
        int $type,
        User $user,
        TextExtractorService $extractor,
        array &$stats,
    ): void {
        $entries = scandir($dir) ?: [];

        // Collect PDF basenames to skip DOCX twins
        $pdfBasenames = [];
        foreach ($entries as $f) {
            if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'pdf') {
                $pdfBasenames[pathinfo($f, PATHINFO_FILENAME)] = true;
            }
        }

        $hasFiles = false;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                $this->line("\n📁 {$entry}");
                $this->importDirectory($path, $entry, $batch, $type, $user, $extractor, $stats);
                continue;
            }

            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (! in_array($ext, ['pdf', 'docx', 'doc', 'txt', 'jpg', 'jpeg', 'png'], true)) continue;

            // Skip non-PDF twin if PDF exists
            if (in_array($ext, ['docx', 'doc'], true) && isset($pdfBasenames[pathinfo($entry, PATHINFO_FILENAME)])) {
                $stats['skipped']++;
                continue;
            }

            $hasFiles = true;
            $this->importFile($path, $entry, $folderName, $batch, $type, $user, $extractor, $stats);
        }

        if (! $hasFiles && $dir === $this->argument('source')) {
            $this->warn('لا توجد ملفات مدعومة في المجلد الجذر.');
        }
    }

    private function importFile(
        string $absolutePath,
        string $originalName,
        string $folderName,
        string $batch,
        int $type,
        User $user,
        TextExtractorService $extractor,
        array &$stats,
    ): void {
        try {
            $title = $this->titleFromFilename($originalName);
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png'], true);

            // Idempotent — skip if already exists
            if (LegalDocument::where('org_id', $user->org_id)->where('title', $title)->where('kind', LegalDocument::KIND_DOCUMENT)->exists()) {
                $this->line("  ⊘ موجود: {$title}");
                $stats['skipped']++;
                return;
            }

            // Copy file to storage
            $storedName = 'documents/batch_' . uniqid() . '.' . $ext;
            $stream = fopen($absolutePath, 'rb');
            Storage::disk('public')->put($storedName, $stream);
            if (is_resource($stream)) fclose($stream);

            // Extract text (skip for images — they'd need OCR)
            $content = null;
            if (! $isImage) {
                $content = $extractor->extract(Storage::disk('public')->path($storedName));
            }

            if ($content !== null && $content !== '') {
                $stats['text_ok']++;
            } else {
                $stats['text_empty']++;
            }

            DB::transaction(function () use ($storedName, $originalName, $absolutePath, $title, $content, $folderName, $batch, $type, $user) {
                $doc = LegalDocument::create([
                    'org_id'        => $user->org_id,
                    'title'         => $title,
                    'type'          => $type,
                    'kind'          => LegalDocument::KIND_DOCUMENT,
                    'summary'       => $folderName,
                    'content'       => $content ?: '',
                    'source_entity' => $folderName,
                    'file_path'     => $storedName,
                    'file_name'     => $originalName,
                    'file_size'     => filesize($absolutePath) ?: 0,
                    'uploaded_by'   => $user->id,
                    'is_private'    => false,
                    'metadata'      => [
                        'import_batch'      => $batch,
                        'folder_origin'     => $folderName,
                        'extraction_status' => $content ? 'ready' : 'empty',
                    ],
                ]);

                $folder = Folder::firstOrCreate(
                    ['org_id' => $user->org_id, 'name' => $folderName],
                    ['owner_id' => $user->id, 'description' => $folderName],
                );
                $folder->documents()->syncWithoutDetaching([
                    $doc->id => ['added_by' => $user->id],
                ]);
            });

            $this->line("  ✓ {$title}");
            $stats['created']++;
        } catch (\Throwable $e) {
            $stats['failed']++;
            $this->error("  ✗ فشل {$originalName}: " . mb_substr($e->getMessage(), 0, 200));
        }
    }

    private function titleFromFilename(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/^[\p{So}\p{Sk}_\s]+/u', '', $base) ?? $base;
        $base = preg_replace('/[\p{So}\p{Sk}_\s]+$/u', '', $base) ?? $base;
        $base = str_replace('_', ' ', $base);
        return trim(preg_replace('/\s+/u', ' ', $base) ?? $base);
    }
}
