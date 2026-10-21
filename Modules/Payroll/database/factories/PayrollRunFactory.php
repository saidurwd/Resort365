<?php

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Database\Factories\Concerns\ResolvesReferences;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Models\PayrollRun;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    use ResolvesReferences;

    protected $model = PayrollRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['property_id' => $this->propertyId(), 'run_no' => 'PRUN-'.fake()->unique()->numerify('####-#####'), 'period' => now()->startOfMonth()->subMonths(fake()->unique()->numberBetween(13, 5000))->toDateString(), 'status' => RunStatus::Draft];
    }
}
