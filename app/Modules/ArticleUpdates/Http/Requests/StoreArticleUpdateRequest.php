<?php

namespace Modules\ArticleUpdates\Http\Requests;

use App\Models\ArticleUpdate;
use Illuminate\Foundation\Http\FormRequest;

class StoreArticleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document !== null
            && ($this->user()?->can('createForDocument', [ArticleUpdate::class, $document]) ?? false);
    }

    public function rules(): array
    {
        return [
            'article_label'      => ['required', 'string', 'max:100'],
            'update_date'        => ['required', 'date'],
            'decree_number'      => ['nullable', 'string', 'max:100'],
            'decree_url'         => ['nullable', 'url', 'max:500'],
            'body'               => ['required', 'string', 'max:10000'],
            'source_document_id' => ['nullable', 'integer', 'exists:legal_documents,id'],
        ];
    }
}
