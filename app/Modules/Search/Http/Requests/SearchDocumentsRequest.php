<?php

namespace Modules\Search\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchDocumentsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q'    => ['required', 'string', 'min:2', 'max:300'],
            'type' => ['nullable', 'integer', 'between:1,7'],
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'size' => ['nullable', 'integer', 'min:1', 'max:50'],
            'ai'   => ['nullable', 'boolean'],
        ];
    }

    public function term(): string
    {
        return (string) $this->input('q');
    }

    public function page(): int
    {
        return (int) ($this->input('page') ?? 1);
    }

    public function size(): int
    {
        return (int) ($this->input('size') ?? 20);
    }

    public function useAi(): bool
    {
        return filter_var($this->input('ai', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function filters(): array
    {
        return [
            'org_id' => $this->user()?->org_id,
            'type'   => $this->input('type'),
            'from'   => $this->input('from'),
            'to'     => $this->input('to'),
        ];
    }
}
