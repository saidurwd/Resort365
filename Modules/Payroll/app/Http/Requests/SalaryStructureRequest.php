<?php

namespace Modules\Payroll\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payroll\Enums\CalcType;

class SalaryStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.structure.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:300'],
            'is_active' => ['nullable', 'boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.salary_component_id' => ['required', 'integer', TenantRule::exists('salary_components')],
            'lines.*.calc_type' => ['required', Rule::enum(CalcType::class)],
            'lines.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'lines.*.percent_of' => ['nullable', 'string', 'max:20'],
            'lines.*.formula' => ['nullable', 'string', 'max:300'],
        ];
    }
}
