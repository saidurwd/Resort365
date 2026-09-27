<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::limit(Str::slug($name, ''), 20, '').fake()->unique()->numberBetween(10, 99),
            'name' => $name,
            'email' => fake()->unique()->companyEmail(),
            'status' => TenantStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::Suspended,
            'suspended_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => TenantStatus::Cancelled]);
    }

    public function trial(): static
    {
        return $this->state(fn (): array => [
            'status' => TenantStatus::Trial,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
