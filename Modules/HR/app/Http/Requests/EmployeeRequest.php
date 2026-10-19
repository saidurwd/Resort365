<?php

namespace Modules\HR\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\HR\Enums\EmploymentType;
use Modules\HR\Enums\Gender;
use Modules\HR\Enums\MaritalStatus;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.employee.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality_code' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'code')],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'array'],
            'address.line1' => ['nullable', 'string', 'max:150'],
            'address.line2' => ['nullable', 'string', 'max:150'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'emergency_contact' => ['nullable', 'array'],
            'emergency_contact.name' => ['nullable', 'string', 'max:100'],
            'emergency_contact.relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'passport_no' => ['nullable', 'string', 'max:50'],
            'passport_expiry' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'department_id' => ['required', 'integer', TenantRule::exists('departments')],
            'designation_id' => ['required', 'integer', TenantRule::exists('designations')],
            'grade_id' => ['nullable', 'integer', TenantRule::exists('grades')],
            'manager_id' => ['nullable', 'integer', TenantRule::exists('employees')],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'joined_on' => ['required', 'date'],
            'probation_ends_on' => ['nullable', 'date', 'after_or_equal:joined_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The validated data. People without hr.employee.view-sensitive never see or change the national ID, passport or
     * bank account number, so those fields are dropped for them.
     *
     * @return array<string, mixed>
     */
    public function employeeData(): array
    {
        $data = $this->validated();

        if (! ($this->user()?->can('hr.employee.view-sensitive') ?? false)) {
            unset($data['national_id'], $data['passport_no'], $data['bank_account_no']);
        }

        return $data;
    }
}
