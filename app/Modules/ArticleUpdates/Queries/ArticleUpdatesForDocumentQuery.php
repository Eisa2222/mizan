<?php

namespace Modules\ArticleUpdates\Queries;

use App\Models\LegalDocument;
use Illuminate\Database\Eloquent\Collection;

class ArticleUpdatesForDocumentQuery
{
    public function run(LegalDocument $document): Collection
    {
        return $document->articleUpdates()
            ->with(['creator:id,name', 'sourceDocument:id,title'])
            ->get();
    }
}
