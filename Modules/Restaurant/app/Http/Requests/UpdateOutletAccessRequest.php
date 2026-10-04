<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The users × outlets grid of the current property: users and outlets of the tenant.
 */
class UpdateOutletAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.access.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'users' => ['required', 'array'],
            'users.*' => ['integer', TenantRule::exists('users')],
            'access' => ['nullable', 'array'],
            'access.*' => ['array'],
            'access.*.*' => ['integer', TenantRule::exists('users')],
        ];
    }
}
