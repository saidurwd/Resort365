<?php

namespace Modules\Procurement\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.requisition.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'gt:0', 'max:99999999999'],
            'department_id' => ['nullable', 'integer'],
        ];
    }
}
