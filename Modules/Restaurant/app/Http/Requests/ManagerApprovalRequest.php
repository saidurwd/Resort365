<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Services\ManagerApprovals;

/**
 * A manager approving an action on this terminal with their PIN (JSON).
 */
class ManagerApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(array_keys(ManagerApprovals::ACTIONS))],
            'manager_id' => ['required', 'integer', TenantRule::exists('users')],
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/'],
            'subject_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
