<?php

namespace Modules\IAM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * Creates users for the current tenant (run inside TenantContext::run()).
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Password123'),
            'remember_token' => Str::random(10),
            'status' => UserStatus::Active,
            'locale' => 'en',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Inactive, 'deactivated_at' => now()]);
    }

    public function invited(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Invited,
            'password' => null,
            'email_verified_at' => null,
            'invited_at' => now(),
        ]);
    }
}
