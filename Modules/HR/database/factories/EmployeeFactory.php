<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Database\Factories\Concerns\ResolvesReferences;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\EmploymentType;
use Modules\HR\Enums\Gender;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    use ResolvesReferences;

    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(), 'employee_no' => 'EMP-'.fake()->unique()->numerify('#####'), 'first_name' => fake()->firstName(), 'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(Gender::cases()), 'date_of_birth' => fake()->dateTimeBetween('-50 years', '-20 years')->format('Y-m-d'), 'nationality_code' => null,
            'email' => fake()->unique()->safeEmail(), 'phone' => fake()->numerify('01#########'), 'national_id' => fake()->numerify('##########'),
            'department_id' => $this->departmentId(), 'designation_id' => Designation::factory(), 'employment_type' => EmploymentType::Permanent,
            'joined_on' => now()->subYear()->toDateString(), 'status' => EmployeeStatus::Active,
        ];
    }
}
