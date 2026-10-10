<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Enums\PartyType;

/**
 * A manual journal entry as a draft: header and lines (account, debit or credit, dimensions).
 */
class JournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.journal.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:300'],
            'reference' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.account_id' => ['required', 'integer', TenantRule::exists('accounts')],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lines.*.description' => ['nullable', 'string', 'max:300'],
            'lines.*.property_id' => ['nullable', 'integer'],
            'lines.*.department_id' => ['nullable', 'integer'],
            'lines.*.party_type' => ['nullable', Rule::enum(PartyType::class)],
            'lines.*.party_id' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::in(['save', 'post'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['lines.*.account_id' => __('account'), 'lines.*.debit' => __('debit'), 'lines.*.credit' => __('credit')];
    }
}
