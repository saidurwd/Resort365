<?php

namespace Modules\IAM\Http\Requests;

use App\Support\Authorization\PermissionRegistry;
use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\IAM\Models\ApiToken;

/**
 * A new API token for yourself (Step 8.1): a name, the permissions it may use, optionally some properties and an end date.
 */
class CreateApiTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ApiToken::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(app(PermissionRegistry::class)->names())],
            'property_ids' => ['nullable', 'array'],
            'property_ids.*' => ['integer', TenantRule::exists('properties')],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['abilities.required' => __('Choose at least one thing the token may do.')];
    }

    public function expiresAt(): ?CarbonImmutable
    {
        $date = $this->validated('expires_at');

        return is_string($date) ? CarbonImmutable::parse($date)->endOfDay() : null;
    }

    /**
     * @return list<int>|null
     */
    public function propertyIds(): ?array
    {
        $ids = $this->validated('property_ids');

        return is_array($ids) && $ids !== [] ? array_values(array_map(intval(...), $ids)) : null;
    }
}
