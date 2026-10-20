<?php

namespace Modules\Payroll\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payroll\Enums\LoanKind;
use Modules\Payroll\Enums\PayMethod;

class LoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.loan.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'kind' => ['required', Rule::enum(LoanKind::class)],
            'principal' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'installments' => ['required', 'integer', 'min:1', 'max:36'],
            'first_month' => ['required', 'date_format:Y-m'],
            'disbursed_on' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['required', Rule::enum(PayMethod::class)],
            'reason' => ['nullable', 'string', 'max:300'],
        ];
    }
}
