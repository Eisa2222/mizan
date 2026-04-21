<?php

namespace Modules\Folders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFolderDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folder = $this->route('folder');

        return $folder !== null
            && $this->user() !== null
            && $folder->isAccessibleBy($this->user());
    }

    public function rules(): array
    {
        return [
            'title'         => ['required', 'string', 'max:255'],
            'type'          => ['required', 'integer', 'between:1,7'],
            'summary'       => ['nullable', 'string', 'max:5000'],
            'content'       => ['nullable', 'string'],
            'source_entity' => ['nullable', 'string', 'max:200'],
            'file'          => ['nullable', 'file', 'mimes:pdf,doc,docx,txt', 'max:20480'],
        ];
    }
}
