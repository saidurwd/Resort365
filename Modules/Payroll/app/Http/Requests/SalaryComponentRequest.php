<?php

namespace Modules\Payroll\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;

class SalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.component.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(ComponentKind::class)],
            'is_taxable' => ['nullable', 'boolean'],
            'frequency' => ['required', Rule::enum(ComponentFrequency::class)],
            'annual_times' => ['nullable', 'integer', 'min:1', 'max:12'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
