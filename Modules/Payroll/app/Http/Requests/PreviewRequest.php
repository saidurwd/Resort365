<?php

namespace Modules\Payroll\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The optional inputs of the structure and employee salary pages: a sample basic, a tax category and area, a date.
 */
class PreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('payroll.structure.view') ?? false) || ($this->user()?->can('payroll.salary.view') ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'basic' => ['nullable', 'numeric', 'gt:0', 'max:999999999'],
            'category' => ['nullable', 'string', 'max:30'],
            'area' => ['nullable', 'string', 'max:30'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
