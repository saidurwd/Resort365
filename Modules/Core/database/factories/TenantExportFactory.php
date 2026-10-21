<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Enums\TenantExportStatus;
use Modules\Core\Models\TenantExport;

/**
 * @extends Factory<TenantExport>
 */
class TenantExportFactory extends Factory
{
    protected $model = TenantExport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['requested_by' => null, 'requested_by_name' => fake()->name(), 'status' => TenantExportStatus::Queued];
    }
}
