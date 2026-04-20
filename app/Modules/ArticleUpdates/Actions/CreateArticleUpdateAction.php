<?php

namespace Modules\ArticleUpdates\Actions;

use App\Models\ArticleUpdate;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Creates a manual ArticleUpdate for a document. If a source document is
 * referenced it must live in the same organization — otherwise we throw
 * a ValidationException so the controller can surface a field error.
 */
class CreateArticleUpdateAction
{
    /**
     * @param  array{article_label:string, update_date:string, decree_number:?string, decree_url:?string, body:string, source_document_id:?int}  $data
     */
    public function execute(LegalDocument $document, User $user, array $data): ArticleUpdate
    {
        if (! empty($data['source_document_id'])) {
            $source = LegalDocument::find($data['source_document_id']);

            if ($source === null || $source->org_id !== $document->org_id) {
                throw ValidationException::withMessages([
                    'source_document_id' => __('article-updates.errors.source_cross_org'),
                ]);
            }
        }

        return ArticleUpdate::create([
            ...$data,
            'document_id'    => $document->id,
            'auto_generated' => false,
            'created_by'     => $user->id,
        ]);
    }
}
