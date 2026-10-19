<?php

use Modules\HR\Actions\AssignRoster;
use Modules\HR\Actions\SaveEmployee;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Shift;
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

/**
 * A shift at the test tenant.
 *
 * @param  array<string, mixed>  $overrides
 */
function hrShift(string $code = 'DAY', string $start = '09:00', string $end = '17:00', array $overrides = []): Shift
{
    return booking(fn (): Shift => Shift::query()->where('code', $code)->first() ?? Shift::factory()->create(
        ['code' => $code, 'name' => $code.' shift', 'starts_at' => $start.':00', 'ends_at' => $end.':00', ...$overrides],
    ));
}

/**
 * Rosters employees on a shift (null = day off) for the dates.
 *
 * @param  list<int>  $employeeIds
 * @param  list<int>|null  $weekdays
 */
function hrRoster(array $employeeIds, string $from, string $to, ?int $shiftId, ?array $weekdays = null): int
{
    return booking(fn (): int => app(AssignRoster::class)->handle($employeeIds, $from, $to, $shiftId, $weekdays));
}
