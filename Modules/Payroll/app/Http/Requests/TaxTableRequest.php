<?php

namespace Modules\Payroll\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TaxTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.tax.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'tax_year' => ['required', 'string', 'max:20'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'exemption_fraction' => ['required', 'string', 'max:20'],
            'exemption_cap' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['nullable', 'boolean'],
            'thresholds' => ['required', 'array', 'min:1'],
            'thresholds.*.code' => ['nullable', 'string', 'max:30'],
            'thresholds.*.label' => ['nullable', 'string', 'max:100'],
            'thresholds.*.amount' => ['nullable', 'numeric', 'min:0'],
            'minimum_tax' => ['nullable', 'array'],
            'minimum_tax.*.code' => ['nullable', 'string', 'max:30'],
            'minimum_tax.*.label' => ['nullable', 'string', 'max:100'],
            'minimum_tax.*.amount' => ['nullable', 'numeric', 'min:0'],
            'slabs' => ['required', 'array', 'min:1'],
            'slabs.*.width' => ['nullable', 'numeric', 'min:0'],
            'slabs.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
