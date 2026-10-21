<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BudgetLinesRequest extends FormRequest
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
        return [
            'lines' => ['nullable', 'array', 'max:500'],
            'lines.*.account_id' => ['required', 'integer', TenantRule::exists('accounts')],
            'lines.*.department_id' => ['nullable', 'integer', TenantRule::exists('departments')],
            'lines.*.months' => ['required', 'array', 'size:12'],
            'lines.*.months.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
