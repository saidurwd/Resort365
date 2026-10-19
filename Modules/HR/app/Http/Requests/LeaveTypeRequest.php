<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\EmploymentType;
use Modules\HR\Enums\Gender;
use Modules\HR\Enums\LeaveAccrual;

class LeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.leave-type.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:100'],
            'is_paid' => ['nullable', 'boolean'],
            'has_balance' => ['nullable', 'boolean'],
            'counts_non_working_days' => ['nullable', 'boolean'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'color' => ['nullable', Rule::in(['primary', 'secondary', 'success', 'danger', 'warning', 'info'])],
            'document_after_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
            'annual_days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'accrual' => ['nullable', Rule::enum(LeaveAccrual::class)],
            'carry_forward_max' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'min_service_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'employment_types' => ['nullable', 'array'],
            'employment_types.*' => [Rule::enum(EmploymentType::class)],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:366'],
        ];
    }
}
