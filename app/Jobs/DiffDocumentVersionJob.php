<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\ArticleUpdate;
use App\Models\DocumentVersion;
use App\Services\DocumentDiffService;
use App\Services\ElasticsearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DiffDocumentVersionJob
 * ──────────────────────
 * When a user uploads a new version of a document, this job:
 *   1. Diffs the new content against the *current* document content
 *      via DocumentDiffService — produces a list of added/modified/removed
 *      articles by label.
 *   2. Creates an ArticleUpdate row (auto_generated=true) for each change.
 *   3. Replaces the document's content with the new version's content
 *      and triggers Elasticsearch reindex (which re-chunks via
 *      DocumentChunker as the existing flow does).
 *   4. Notifies the version uploader with a summary.
 *
 * On any error: marks failure on the version row and notifies the user.
 */
class DiffDocumentVersionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public DocumentVersion $version)
    {
    }

    public function handle(DocumentDiffService $differ, ElasticsearchService $es): void
    {
        $version = $this->version->fresh();
        if (! $version) return;

        $document = $version->document;
        if (! $document) {
            Log::warning('DiffDocumentVersionJob: parent document missing', [
                'version_id' => $version->id,
            ]);
            return;
        }

        $newContent = (string) ($version->content ?? '');
        if (trim($newContent) === '') {
            $this->notifyFailure($version, 'النسخة الجديدة لا تحوي نصاً قابلاً للمقارنة.');
            return;
        }

        try {
            // 1. Diff old vs new BEFORE we touch the document content.
            $changes = $differ->diff($document, $newContent);

            // 2. Persist auto-generated ArticleUpdate rows for each change.
            DB::transaction(function () use ($document, $version, $changes) {
                foreach ($changes as $change) {
                    if ($change['change'] === 'removed') {
                        // Skip removals — they'd produce empty bodies.
                        // Modifications and additions are what users care about.
                        continue;
                    }
                    ArticleUpdate::create([
                        'document_id'        => $document->id,
                        'article_label'      => $change['label'],
                        'update_date'        => now()->toDateString(),
                        'decree_number'      => null,
                        'decree_url'         => null,
                        'body'               => $change['new'],
                        'source_document_id' => null,
                        'auto_generated'     => true,
                        'created_by'         => $version->uploaded_by,
                    ]);
                }

                // 3. Swap in the new content + bump file metadata to point at
                //    the latest version. The old file is preserved on disk
                //    via DocumentVersion::file_path so users can download it.
                $document->update([
                    'content'   => $version->content,
                    'file_path' => $version->file_path,
                    'file_name' => $version->file_name,
                    'file_size' => $version->file_size,
                ]);
            });

            // 4. Reindex (re-chunks via DocumentChunker, then bulk-indexes).
            $es->reindexDocument($document->fresh());

            // 5. Tell the uploader what happened.
            $modifiedCount = $changes->where('change', 'modified')->count();
            $addedCount = $changes->where('change', 'added')->count();
            $removedCount = $changes->where('change', 'removed')->count();

            AppNotification::notify(
                userId: $version->uploaded_by,
                type: 'version_diffed',
                title: 'تمت معالجة النسخة الجديدة',
                body: sprintf(
                    'تم اكتشاف %d مادة معدّلة، %d مادة جديدة، و%d مادة محذوفة في النسخة #%d من "%s".',
                    $modifiedCount,
                    $addedCount,
                    $removedCount,
                    $version->version_number,
                    $document->title
                ),
                data: [
                    'document_id' => $document->id,
                    'version_id'  => $version->id,
                    'modified'    => $modifiedCount,
                    'added'       => $addedCount,
                    'removed'     => $removedCount,
                ]
            );
        } catch (Throwable $e) {
            Log::error('DiffDocumentVersionJob failed', [
                'version_id'  => $version->id,
                'document_id' => $document->id,
                'error'       => $e->getMessage(),
            ]);
            $this->notifyFailure($version, 'خطأ أثناء مقارنة النسخ: ' . $e->getMessage());
        }
    }

    private function notifyFailure(DocumentVersion $version, string $reason): void
    {
        AppNotification::notify(
            userId: $version->uploaded_by,
            type: 'version_failed',
            title: 'فشل معالجة النسخة الجديدة',
            body: $reason,
            data: ['document_id' => $version->document_id, 'version_id' => $version->id]
        );
    }
}
