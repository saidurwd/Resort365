<?php

namespace Modules\HR\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LinkUserRequest extends FormRequest
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
        return ['user_id' => ['nullable', 'integer', TenantRule::exists('users')]];
    }
}
