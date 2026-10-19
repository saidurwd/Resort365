<?php

namespace Modules\HR\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\HalfDay;

class LeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('hr.leave.request') ?? false) || ($this->user()?->can('hr.leave.manage') ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', TenantRule::exists('employees')],
            'leave_type_id' => ['required', 'integer', TenantRule::exists('leave_types')],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'half_day' => ['nullable', Rule::enum(HalfDay::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
