<?php

use App\Support\Authorization\DefaultRole;
use Modules\Core\Actions\SeedApprovalWorkflows;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\HR\Actions\AssignRoster;
use Modules\HR\Actions\SaveEmployee;
use Modules\HR\Actions\SeedLeaveTypes;
use Modules\HR\Actions\SubmitLeaveRequest;
use Modules\HR\Enums\HalfDay;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Models\Shift;
use Modules\IAM\Models\User;
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

/** Approval workflows and the standard leave types (new tenants get them when created through CreateTenant, factories do not). */
function lvPrepare(): void
{
    booking(function (): void {
        app(SeedApprovalWorkflows::class)->handle();
        app(SeedLeaveTypes::class)->handle();
    });
}

function lvType(string $code): LeaveType
{
    return booking(fn (): LeaveType => LeaveType::query()->where('code', $code)->firstOrFail());
}

/** Today plus some days, as a date. */
function lvDay(int $ahead): string
{
    return now()->addDays($ahead)->toDateString();
}

/** Gives the employee a balance of a leave type for this year and next (requests near New Year touch both). */
function lvBalance(Employee $employee, string $code, string $entitled = '10.0'): void
{
    booking(function () use ($employee, $code, $entitled): void {
        foreach ([(int) now()->format('Y'), (int) now()->addYear()->format('Y')] as $year) {
            LeaveBalance::query()->updateOrCreate(['employee_id' => $employee->id, 'leave_type_id' => lvType($code)->id, 'year' => $year], ['entitled' => $entitled, 'carried_forward' => '0.0', 'adjusted' => '0.0', 'used' => '0.0', 'pending' => '0.0']);
        }
    });
}

/** The balance figures summed over this year and next. */
function lvSum(Employee $employee, string $code, string $column): string
{
    return booking(fn (): string => number_format((float) LeaveBalance::query()->where(['employee_id' => $employee->id, 'leave_type_id' => lvType($code)->id])->sum($column), 1, '.', ''));
}

function lvUser(DefaultRole $role = DefaultRole::FrontDeskAgent): User
{
    return tenantUserAs(tenant('sunrise'), $role);
}

function lvSubmit(Employee $employee, string $code, int $fromAhead, int $toAhead, ?HalfDay $half = null, ?int $by = null): LeaveRequest
{
    $by ??= lvUser()->id;

    return booking(fn (): LeaveRequest => app(SubmitLeaveRequest::class)->handle(Employee::query()->findOrFail($employee->id), lvType($code), lvDay($fromAhead), lvDay($toAhead), $half, 'Family trip', $by));
}

function lvApprove(LeaveRequest $leave, DefaultRole $role = DefaultRole::GeneralManager): void
{
    booking(fn () => app(ApprovalEngine::class)->approve((int) lvFresh($leave)->approval_request_id, lvUser($role)->id));
}

function lvFresh(LeaveRequest $leave): LeaveRequest
{
    return booking(fn (): LeaveRequest => LeaveRequest::query()->findOrFail($leave->id));
}

/** A day-shift roster for the employee from today for the coming 60 days. */
function lvRoster(Employee $employee): Shift
{
    $shift = hrShift('DAY');
    hrRoster([$employee->id], lvDay(0), lvDay(60), $shift->id);

    return $shift;
}
