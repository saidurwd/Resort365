<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BudgetCopyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.budget.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['from_year_id' => ['required', 'integer', TenantRule::exists('fiscal_years')], 'change' => ['required', 'numeric', 'min:-100', 'max:1000']];
    }
}
