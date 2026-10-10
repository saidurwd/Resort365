<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StatementImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.bank.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'integer', TenantRule::exists('bank_accounts')],
            'file' => ['required', 'file', 'max:2048', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
            'closing_balance' => ['nullable', 'numeric'],
        ];
    }
}
