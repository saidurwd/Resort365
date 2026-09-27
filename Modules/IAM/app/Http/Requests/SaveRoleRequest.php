<?php

namespace Modules\IAM\Http\Requests;

use App\Support\Authorization\PermissionRegistry;
use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\IAM\Models\Role;

/**
 * Create or update a custom role. Authorization: route `can:` middleware plus RolePolicy in the controller.
 */
class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? ($this->user()?->can('update', $role) ?? false)
            : ($this->user()?->can('create', Role::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:100', TenantRule::unique('roles', 'name')->ignore($role instanceof Role ? $role->id : null)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(app(PermissionRegistry::class)->names())],
        ];
    }
}
