<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Each role's largest discount %.
 */
class DiscountLimitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.discount-limit.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'limits' => ['required', 'array'],
            'limits.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'roles' => ['required', 'array'],
            'roles.*' => ['integer', TenantRule::exists('roles')],
        ];
    }

    /**
     * @return array<int, string|null>
     */
    public function limits(): array
    {
        $limits = (array) $this->validated('limits');

        return collect((array) $this->validated('roles'))->mapWithKeys(fn ($roleId): array => [(int) $roleId => isset($limits[$roleId]) ? (string) $limits[$roleId] : null])->all();
    }
}
