<?php

namespace Modules\Folders\Actions;

use App\Models\Folder;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ElasticsearchService;
use App\Services\TextExtractorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Uploads a document into a folder as private-to-folder-members, extracts
 * its text when no content was pasted, and reindexes it in Elasticsearch.
 */
class UploadFolderDocumentAction
{
    public function __construct(
        private readonly TextExtractorService $extractor,
        private readonly ElasticsearchService $elasticsearch,
    ) {
    }

    /**
     * @param  array{title:string, type:int, summary:?string, content:?string, source_entity:?string}  $data
     */
    public function execute(Folder $folder, User $user, array $data, ?UploadedFile $file): LegalDocument
    {
        $payload = [
            'org_id'        => $folder->org_id,
            'title'         => $data['title'],
            'type'          => $data['type'],
            'summary'       => $data['summary'] ?? null,
            'content'       => $data['content'] ?? null,
            'source_entity' => $data['source_entity'] ?? null,
            'uploaded_by'   => $user->id,
            'is_private'    => true,
        ];

        if ($file !== null) {
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

        $folder->documents()->attach($document->id, ['added_by' => $user->id]);

        $this->elasticsearch->reindexDocument($document);

        return $document;
    }
}
