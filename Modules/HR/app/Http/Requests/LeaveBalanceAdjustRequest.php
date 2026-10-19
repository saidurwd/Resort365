<?php

namespace Modules\HR\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LeaveBalanceAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.leave.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'leave_type_id' => ['required', 'integer', TenantRule::exists('leave_types')],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'days' => ['required', 'numeric', 'between:-366,366'],
            'reason' => ['required', 'string', 'max:300'],
        ];
    }
}
