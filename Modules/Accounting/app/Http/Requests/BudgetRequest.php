<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BudgetRequest extends FormRequest
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
        $rules = ['name' => ['required', 'string', 'max:120'], 'notes' => ['nullable', 'string', 'max:500']];

        if ($this->isMethod('POST')) {
            $rules['fiscal_year_id'] = ['required', 'integer', TenantRule::exists('fiscal_years')];
            $rules['property_id'] = ['nullable', 'integer', TenantRule::exists('properties')];
        }

        return $rules;
    }
}
