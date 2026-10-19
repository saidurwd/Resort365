<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Database\Factories\Concerns\ResolvesReferences;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Roster;
use Modules\HR\Models\Shift;

/**
 * @extends Factory<Roster>
 */
class RosterFactory extends Factory
{
    use ResolvesReferences;

    protected $model = Roster::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['property_id' => $this->propertyId(), 'employee_id' => Employee::factory(), 'work_date' => now()->toDateString(), 'shift_id' => Shift::factory()];
    }
}
