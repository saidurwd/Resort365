<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.voucher.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:income,expense'],
            'voucher_date' => ['required', 'date_format:Y-m-d'],
            'account_id' => ['required', 'integer', TenantRule::exists('accounts')],
            'cash_account_id' => ['required', 'integer', TenantRule::exists('accounts')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'department_id' => ['nullable', 'integer'],
            'payee' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:300'],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
