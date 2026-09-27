<?php

namespace Modules\IAM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\IAM\Models\Role;

/**
 * Custom roles for the current tenant (run inside TenantContext::run()).
 *
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'guard_name' => 'web',
            'description' => fake()->sentence(),
        ];
    }
}
