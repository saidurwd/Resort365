<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.bank.reconcile') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'statement_line_id' => ['required', 'integer', TenantRule::exists('bank_statement_lines')],
            'journal_line_id' => ['required', 'integer', TenantRule::exists('journal_lines')],
        ];
    }
}
