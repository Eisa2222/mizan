<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('documents:extract {--id= : Limit to a single document} {--force : Re-extract even if content already exists}')]
#[Description('Extract text from uploaded files (PDF/DOCX/TXT) into the content column, then re-chunk and re-index')]
class ExtractAndIndex extends Command
{
    public function handle(TextExtractorService $extractor, ElasticsearchService $es): int
    {
        $query = LegalDocument::query()->whereNotNull('file_path');
        if ($id = $this->option('id')) {
            $query->where('id', $id);
        } elseif (!$this->option('force')) {
            // skip docs that already have content
            $query->where(function ($q) {
                $q->whereNull('content')->orWhere('content', '');
            });
        }

        $docs = $query->get();
        if ($docs->isEmpty()) {
            $this->info('No documents need extraction.');
            return self::SUCCESS;
        }

        $this->info("Found {$docs->count()} document(s) with files...");

        foreach ($docs as $doc) {
            $abs = Storage::disk('public')->path($doc->file_path);
            if (!is_file($abs)) {
                $this->warn("  ✗ #{$doc->id} file missing: {$doc->file_path}");
                continue;
            }

            $text = $extractor->extract($abs);
            if (!$text) {
                $this->warn("  ✗ #{$doc->id} \"{$doc->title}\" — extraction failed");
                continue;
            }

            // Clear any stale extraction failure markers from a prior OCR
            // attempt — if synchronous extraction works now, the document
            // is no longer in a "failed" state and the show page should
            // not display the warning banner.
            $meta = $doc->metadata ?? [];
            unset($meta['extraction_status'], $meta['extraction_error'], $meta['failed_at']);
            $meta['extraction_status'] = 'extracted';
            $meta['extracted_at'] = now()->toIso8601String();

            $doc->update([
                'content' => $text,
                'metadata' => $meta,
            ]);
            $chunks = $es->reindexDocument($doc->fresh());
            $this->line(sprintf("  ✓ #%d \"%s\" — %d chars, %d chunks", $doc->id, mb_substr($doc->title, 0, 40), mb_strlen($text), $chunks));
        }

        $this->newLine();
        $this->info('✓ Done.');
        return self::SUCCESS;
    }
}
