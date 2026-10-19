<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Database\Factories\Concerns\ResolvesReferences;
use Modules\HR\Models\AttendanceImport;

/**
 * @extends Factory<AttendanceImport>
 */
class AttendanceImportFactory extends Factory
{
    use ResolvesReferences;

    protected $model = AttendanceImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['property_id' => $this->propertyId(), 'filename' => 'punches.csv', 'rows_read' => 10, 'days_imported' => 5, 'days_skipped' => 0];
    }
}
