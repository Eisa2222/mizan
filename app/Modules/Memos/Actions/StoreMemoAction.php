<?php

namespace Modules\Memos\Actions;

use App\Jobs\DraftMemoJob;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Persists a memo draft and — when content is available — indexes it in
 * Elasticsearch and triggers the synchronous AI drafting job. Works with
 * either an uploaded file (text extracted inline) or raw pasted content.
 */
class StoreMemoAction
{
    public function __construct(
        private readonly TextExtractorService $extractor,
        private readonly ElasticsearchService $elasticsearch,
    ) {
    }

    public function execute(User $user, string $title, ?string $content, ?UploadedFile $file): LegalDocument
    {
        $payload = [
            'title'       => $title,
            'content'     => $content,
            'org_id'      => $user->org_id,
            'uploaded_by' => $user->id,
            'kind'        => LegalDocument::KIND_MEMO,
            'type'        => 1,
        ];

        if ($file instanceof UploadedFile) {
            $storedPath = $file->store('documents', 'public');

            $payload['file_path'] = $storedPath;
            $payload['file_name'] = $file->getClientOriginalName();
            $payload['file_size'] = $file->getSize();

            if (empty($payload['content'])) {
                $extracted = $this->extractor->extract(Storage::disk('public')->path($storedPath));
                if ($extracted !== null && $extracted !== '') {
                    $payload['content'] = $extracted;
                }
            }
        }

        $document = LegalDocument::create($payload);

        if ($document->content) {
            $this->elasticsearch->reindexDocument($document);
            DraftMemoJob::dispatchSync($document);
        }

        return $document;
    }
}
