<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Enums\AccountType;

/**
 * Adding or changing a ledger account.
 */
class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.account.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $account = $this->route('account');

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9.\-]+$/', TenantRule::unique('accounts', 'code')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:190'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'parent_id' => ['nullable', 'integer', TenantRule::exists('accounts')],
            'is_group' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'usali_department' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:300'],
        ];
    }
}
