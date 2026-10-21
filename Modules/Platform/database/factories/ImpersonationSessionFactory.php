<?php

namespace Modules\Platform\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\Models\ImpersonationSession;
use Modules\Platform\Models\PlatformAdmin;

/**
 * @extends Factory<ImpersonationSession>
 */
class ImpersonationSessionFactory extends Factory
{
    protected $model = ImpersonationSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_admin_id' => PlatformAdmin::factory(), 'tenant_id' => Tenant::factory(), 'user_id' => fake()->numberBetween(1, 1000), 'user_name' => fake()->name(), 'reason' => 'Support request',
            'token_hash' => hash('sha256', fake()->unique()->uuid()), 'expires_at' => now()->addMinute(),
        ];
    }
}
