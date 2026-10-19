<?php

namespace Modules\Payroll\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.salary.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'salary_structure_id' => ['required', 'integer', TenantRule::exists('salary_structures')],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'basic' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'amounts' => ['nullable', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'tax_category' => ['required', 'string', 'max:30'],
            'tax_area' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }
}
