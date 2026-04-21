<?php

namespace Modules\Tenders\Http\Requests;

use App\Models\Tender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenderRequest extends FormRequest
{
    /**
     * Alpine sends empty placeholder entries for blank list items. Filter them
     * out *before* validation runs so `array_values` doesn't leave gaps.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'deliverables' => array_values(array_filter(
                $this->input('deliverables', []),
                fn ($value) => is_string($value) && trim($value) !== '',
            )),
            'special_conditions' => array_values(array_filter(
                $this->input('special_conditions', []),
                fn ($value) => is_string($value) && trim($value) !== '',
            )),
            'boq_items' => array_values(array_filter(
                $this->input('boq_items', []),
                fn ($item) => is_array($item) && trim($item['description'] ?? '') !== '',
            )),
        ]);
    }

    public function rules(): array
    {
        return [
            'title'                   => ['required', 'string', 'max:255'],
            'description'             => ['nullable', 'string', 'max:5000'],
            'scope_input'             => ['required', 'string', 'min:10', 'max:10000'],
            'type'                    => ['required', Rule::in(array_keys(Tender::TYPES))],
            'custom_type'             => ['nullable', 'string', 'max:100'],
            'duration'                => ['nullable', 'string', 'max:100'],
            'deliverables'            => ['nullable', 'array'],
            'deliverables.*'          => ['string', 'max:500'],
            'evaluation_criteria'     => ['nullable', 'array'],
            'special_conditions'      => ['nullable', 'array'],
            'special_conditions.*'    => ['string', 'max:500'],
            'boq_items'               => ['nullable', 'array'],
            'boq_items.*.description' => ['required_with:boq_items', 'string', 'max:500'],
            'boq_items.*.unit'        => ['nullable', 'string', 'max:50'],
            'boq_items.*.quantity'    => ['nullable', 'integer', 'min:1'],
        ];
    }
}
