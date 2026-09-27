<?php

namespace App\Actions\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Actions\Action;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates a tenant (central record). TODO(step-7.4): full onboarding (plan, owner user, setup wizard, defaults).
 */
class CreateTenant extends Action
{
    /**
     * @throws ValidationException
     */
    public function handle(string $slug, string $name, ?string $email = null, TenantStatus $status = TenantStatus::Active): Tenant
    {
        $data = Validator::make(
            ['slug' => $slug, 'name' => $name, 'email' => $email],
            self::rules(),
            ['slug.not_in' => __('The subdomain ":input" is reserved.')],
        )->validate();

        return Tenant::query()->create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $status,
            'trial_ends_at' => $status === TenantStatus::Trial ? now()->addDays(14) : null,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            // A DNS label: lowercase letters, digits and inner hyphens.
            'slug' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', Rule::notIn(config('tenancy.reserved_slugs')), Rule::unique('tenants', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
