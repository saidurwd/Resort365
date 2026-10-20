<?php

namespace Modules\Payroll\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.run.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'include_annual' => ['nullable', 'array'],
            'include_annual.*' => ['string', TenantRule::exists('salary_components', 'code')],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }
}
