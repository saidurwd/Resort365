<?php

use Modules\HR\Actions\SaveEmployee;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\Property\Models\Department;
use Modules\Property\Models\Property;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

/**
 * A department and a designation for the tests' employees.
 *
 * @return array{department: int, designation: int}
 */
function hrBasics(): array
{
    return booking(fn (): array => [
        'department' => (int) (Department::query()->value('id') ?? Department::factory()->create()->id),
        'designation' => (int) (Designation::query()->value('id') ?? Designation::factory()->create(['name' => 'Receptionist'])->id),
    ]);
}

/**
 * Adds an employee through SaveEmployee (at the test property, outside any user's session).
 *
 * @param  array<string, mixed>  $overrides
 */
function hrEmployee(array $overrides = [], ?int $userId = null): Employee
{
    $basics = hrBasics();

    return booking(fn (): Employee => app(SaveEmployee::class)->handle(null, [
        'first_name' => 'Rahim', 'last_name' => 'Uddin', 'department_id' => $basics['department'], 'designation_id' => $basics['designation'], 'employment_type' => 'permanent',
        'joined_on' => '2026-01-05', 'property_id' => bookingIds()['property'], ...$overrides,
    ], $userId));
}

/** The employee as stored now. */
function hrFresh(Employee $employee): Employee
{
    return booking(fn (): Employee => Employee::query()->with(['designation', 'grade', 'manager', 'histories'])->findOrFail($employee->id));
}

function hrSecondProperty(): int
{
    return booking(fn (): int => Property::query()->where('code', 'DHK')->value('id') ?? Property::factory()->create(['code' => 'DHK', 'name' => 'Sunrise Dhaka'])->id);
}
