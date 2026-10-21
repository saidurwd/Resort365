<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\Models\PlatformAuditEntry;

/**
 * @extends Factory<PlatformAuditEntry>
 */
class PlatformAuditEntryFactory extends Factory
{
    protected $model = PlatformAuditEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['admin_name' => fake()->name(), 'action' => 'tenant.suspended', 'summary' => fake()->sentence(5), 'ip' => '127.0.0.1'];
    }
}
