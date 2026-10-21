<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BudgetReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.budget.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'view' => ['nullable', 'in:accounts,months'],
            'from' => ['nullable', 'integer', 'min:1', 'max:12'],
            'to' => ['nullable', 'integer', 'min:1', 'max:12', 'gte:from'],
            'department' => ['nullable', 'integer', TenantRule::exists('departments')],
            'account' => ['nullable', 'integer', TenantRule::exists('accounts')],
            'export' => ['nullable', 'in:xlsx,csv,pdf'],
        ];
    }
}
