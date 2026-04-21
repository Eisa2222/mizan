<?php

namespace Modules\Documents\Actions;

use App\Models\LegalDocument;
use App\Services\ElasticsearchService;
use Illuminate\Support\Facades\Storage;

class DeleteDocumentAction
{
    public function __construct(private readonly ElasticsearchService $elasticsearch)
    {
    }

    public function execute(LegalDocument $document): void
    {
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $this->elasticsearch->deleteDocument($document->id);
        $document->delete();
    }
}
