<?php

/*
| What HR tells Payroll (Step 6.5): who worked in a month, how many days they were absent or on unpaid leave, their overtime,
| and their bank details.
*/
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Actions\RecordAttendance;
use Modules\HR\Contracts\PayrollFacts;
use Modules\HR\DTOs\PayrollEmployee;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Models\Employee;

require_once __DIR__.'/../Support/hr-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

function pfFacts(): PayrollFacts
{
    return app(PayrollFacts::class);
}

it('lists the employees of the property who were employed during the month, by name', function (): void {
    $zed = hrEmployee(['first_name' => 'Zed', 'joined_on' => '2026-01-05']);
    $amy = hrEmployee(['first_name' => 'Amy', 'joined_on' => '2026-03-31']);
    $late = hrEmployee(['first_name' => 'Late', 'joined_on' => '2026-04-01']);
    $gone = hrEmployee(['first_name' => 'Gone', 'joined_on' => '2025-01-05']);
    booking(fn () => Employee::query()->whereKey($gone->id)->update(['exited_on' => '2026-02-28']));
    $left = hrEmployee(['first_name' => 'Left', 'joined_on' => '2025-01-05']);
    booking(fn () => Employee::query()->whereKey($left->id)->update(['exited_on' => '2026-03-10']));

    $names = booking(fn (): array => array_map(fn (PayrollEmployee $e): string => $e->name, pfFacts()->employees(bookingIds()['property'], '2026-03')));

    expect($names)->toBe(['Amy Uddin', 'Left Uddin', 'Zed Uddin'])->and($late->id)->not->toBe($amy->id);
    $first = booking(fn () => pfFacts()->employees(bookingIds()['property'], '2026-03')[0]);
    expect($first->employeeNo)->toStartWith('EMP-')->and($first->joinedOn)->toBe('2026-03-31')->and($first->exitedOn)->toBeNull();
});

it('counts scheduled days, absences, half days and overtime, up to the given date', function (): void {
    $shift = hrShift('DAY', '09:00', '17:00', ['break_minutes' => 30, 'grace_minutes' => 10, 'overtime_after_minutes' => 15]);
    $employee = hrEmployee();
    hrRoster([$employee->id], '2026-03-02', '2026-03-06', $shift->id);
    $record = fn (string $date, ?string $in, ?string $out, ?AttendanceStatus $status = null) => booking(fn () => app(RecordAttendance::class)->handle(hrFresh($employee), $date, $in, $out, $status, null, 1));
    $record('2026-03-02', '09:00', '19:00');
    $record('2026-03-03', '09:00', '17:00');
    $record('2026-03-04', null, null, AttendanceStatus::Absent);
    $record('2026-03-05', '09:00', '13:00', AttendanceStatus::HalfDay);
    // 6 March has no record: an absence.

    $all = booking(fn (): array => pfFacts()->attendance(bookingIds()['property'], '2026-03', '2026-03-31'));
    $early = booking(fn (): array => pfFacts()->attendance(bookingIds()['property'], '2026-03', '2026-03-04'));
    $none = booking(fn (): array => pfFacts()->attendance(bookingIds()['property'], '2026-03', '2026-02-28'));

    expect($all[$employee->id]->scheduledDays)->toBe(5)->and($all[$employee->id]->absentDays)->toBe('2.5')->and($all[$employee->id]->unpaidLeaveDays)->toBe('0.0')->and($all[$employee->id]->overtimeMinutes)->toBe(120)
        ->and($early[$employee->id]->scheduledDays)->toBe(3)->and($early[$employee->id]->absentDays)->toBe('1.0')
        ->and($none[$employee->id]->scheduledDays)->toBe(0)->and($none[$employee->id]->absentDays)->toBe('0.0');
});

it('gives the bank details of an employee, or nothing for someone unknown', function (): void {
    $employee = hrEmployee(['bank_name' => 'City Bank', 'bank_branch' => 'Main', 'bank_account_name' => 'Rahim Uddin', 'bank_account_no' => '1101-555']);

    $bank = booking(fn () => pfFacts()->bankDetails($employee->id));

    expect($bank->bank)->toBe('City Bank')->and($bank->accountNumber)->toBe('1101-555')->and(booking(fn () => pfFacts()->bankDetails(999999)))->toBeNull();
});
