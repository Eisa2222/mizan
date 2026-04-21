<?php

namespace Modules\Documents\Http\Requests;

use App\Models\LegalDocument;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Documents\DTOs\StoreDocumentData;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LegalDocument::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'title_en'         => ['nullable', 'string', 'max:255'],
            'type'             => ['required', 'integer', 'between:1,7'],
            'summary'          => ['nullable', 'string', 'max:5000'],
            'content'          => ['nullable', 'string'],
            'issued_at'        => ['nullable', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'source_entity'    => ['nullable', 'string', 'max:200'],
            'file'             => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,tiff,tif,webp', 'max:20480'],
        ];
    }

    public function toData(): StoreDocumentData
    {
        return new StoreDocumentData(
            orgId:           $this->user()->org_id,
            uploadedBy:      $this->user()->id,
            title:           $this->string('title'),
            titleEn:         $this->input('title_en'),
            type:            (int) $this->input('type'),
            kind:            LegalDocument::KIND_DOCUMENT,
            summary:         $this->input('summary'),
            content:         $this->input('content'),
            issuedAt:        $this->input('issued_at'),
            referenceNumber: $this->input('reference_number'),
            sourceEntity:    $this->input('source_entity'),
            file:            $this->file('file'),
        );
    }
}
