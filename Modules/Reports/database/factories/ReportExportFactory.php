<?php

namespace Modules\Reports\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reports\Enums\ExportFormat;
use Modules\Reports\Enums\ExportStatus;
use Modules\Reports\Models\ReportExport;

/**
 * @extends Factory<ReportExport>
 */
class ReportExportFactory extends Factory
{
    protected $model = ReportExport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->numberBetween(1, 1000), 'report_key' => 'forecast', 'title' => 'Occupancy forecast', 'format' => ExportFormat::Xlsx, 'filters' => ['property_ids' => [1], 'from' => '2026-03-01', 'to' => '2026-03-31'],
            'status' => ExportStatus::Queued, 'rows_count' => 0,
        ];
    }
}
