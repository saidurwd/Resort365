<?php

namespace Modules\Payroll\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PoolParticipantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.pool.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.employee_id' => ['required', 'integer', TenantRule::exists('employees')],
            'participants.*.eligible' => ['nullable', 'boolean'],
            'participants.*.points' => ['required', 'numeric', 'min:0', 'max:1000'],
        ];
    }
}
