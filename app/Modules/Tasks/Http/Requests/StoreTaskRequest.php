<?php

namespace Modules\Tasks\Http\Requests;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Tasks\DTOs\NewTaskData;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Task::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority'    => ['required', 'integer', 'between:1,4'],
            'due_date'    => ['nullable', 'date'],
            'document_id' => ['nullable', 'integer', 'exists:legal_documents,id'],
        ];
    }

    public function toData(): NewTaskData
    {
        return new NewTaskData(
            orgId:       $this->user()->org_id,
            createdById: $this->user()->id,
            title:       $this->string('title'),
            description: $this->input('description'),
            priority:    (int) $this->input('priority'),
            dueDate:     $this->input('due_date'),
            documentId:  $this->input('document_id') !== null ? (int) $this->input('document_id') : null,
        );
    }
}
