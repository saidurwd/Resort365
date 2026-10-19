<?php

/*
| Leave management (Step 6.3): the standard leave types, granting and accruing balances, requests (counting, refusals,
| approval, rejection, cancelling), long leave needing two approvals, and the roster reflecting approved leave. "Done when":
| a leave request is approved and the balance and the roster reflect it.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\HR\Actions\AdjustLeaveBalance;
use Modules\HR\Actions\AssignRoster;
use Modules\HR\Actions\CancelLeaveRequest;
use Modules\HR\Actions\ClearRoster;
use Modules\HR\Actions\DeleteLeaveType;
use Modules\HR\Actions\GrantLeave;
use Modules\HR\Actions\SaveHoliday;
use Modules\HR\Actions\SaveLeaveType;
use Modules\HR\Actions\SeedLeaveTypes;
use Modules\HR\Actions\SubmitLeaveRequest;
use Modules\HR\Enums\HalfDay;
use Modules\HR\Enums\LeaveAccrual;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Listeners\SeedLeaveTypesForNewTenant;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeavePolicy;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Models\Roster;
use Modules\HR\Services\AttendanceReports;
use Modules\HR\Services\LeaveApprover;

require_once __DIR__.'/../Support/hr-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    lvPrepare();
});

it('starts a tenant with the standard leave types and policies, once', function (): void {
    $types = booking(fn (): array => LeaveType::query()->orderBy('code')->pluck('name', 'code')->all());
    $annual = booking(fn (): LeavePolicy => LeavePolicy::query()->where('leave_type_id', lvType('ANN')->id)->firstOrFail());
    $maternity = booking(fn (): LeavePolicy => LeavePolicy::query()->where('leave_type_id', lvType('MAT')->id)->firstOrFail());

    expect(array_keys($types))->toBe(['ANN', 'CAS', 'MAT', 'SICK', 'UNP'])->and((string) $annual->annual_days)->toBe('14.0')->and($annual->accrual)->toBe(LeaveAccrual::Monthly)->and($annual->min_service_months)->toBe(12)
        ->and((string) $annual->carry_forward_max)->toBe('5.0')->and((string) $maternity->annual_days)->toBe('112.0')->and(lvType('MAT')->gender?->value)->toBe('female')->and(lvType('UNP')->has_balance)->toBeFalse()
        ->and(booking(fn (): int => app(SeedLeaveTypes::class)->handle()))->toBe(0);

    $other = Tenant::factory()->create(['slug' => 'other']);
    app(SeedLeaveTypesForNewTenant::class)->handle(new TenantCreated($other));
    expect(app(TenantContext::class)->run($other, fn (): int => LeaveType::query()->count()))->toBe(5);
});

it('saves a leave type with its policy, refuses a duplicate code, and deletes only a type nobody uses', function (): void {
    $save = fn (?LeaveType $type, array $data) => booking(fn (): LeaveType => app(SaveLeaveType::class)->handle($type, $data));
    $study = $save(null, ['code' => 'study', 'name' => 'Study leave', 'annual_days' => '5', 'accrual' => 'yearly', 'carry_forward_max' => '2', 'min_service_months' => 6, 'employment_types' => ['permanent'], 'max_consecutive_days' => 3, 'color' => 'info']);

    expect($study->code)->toBe('STUDY')->and((string) $study->policy?->annual_days)->toBe('5.0')->and($study->policy?->employment_types)->toBe(['permanent'])->and($study->policy?->max_consecutive_days)->toBe(3)
        ->and(fn () => $save(null, ['code' => 'ann', 'name' => 'Again', 'annual_days' => '1']))->toThrow(HrRuleViolated::class, 'already a leave type')
        ->and(fn () => $save(null, ['code' => 'ZERO', 'name' => 'No days', 'annual_days' => '0', 'has_balance' => true]))->toThrow(HrRuleViolated::class, 'needs days per year')
        ->and($save($study, ['code' => 'STUDY', 'name' => 'Study', 'annual_days' => '6', 'employment_types' => []])->policy?->employment_types)->toBeNull();

    booking(fn () => app(DeleteLeaveType::class)->handle($study));
    expect(booking(fn (): bool => LeaveType::query()->whereKey($study->id)->exists()))->toBeFalse();

    $employee = hrEmployee();
    lvBalance($employee, 'CAS');
    expect(fn () => booking(fn () => app(DeleteLeaveType::class)->handle(lvType('CAS'))))->toThrow(HrRuleViolated::class, 'only be made inactive');
});

it('grants balances by policy, accrues monthly, pro-rates joiners, carries forward, and is safe to repeat', function (): void {
    $male = hrEmployee(['first_name' => 'Rahim', 'gender' => 'male', 'joined_on' => '2024-01-10']);
    $female = hrEmployee(['first_name' => 'Nusrat', 'gender' => 'female', 'joined_on' => '2024-01-10']);
    $grant = fn (int $year, string $asOf) => booking(fn (): int => app(GrantLeave::class)->handle($year, CarbonImmutable::parse($asOf)));
    $entitled = fn (string $code, $who): ?string => booking(fn (): ?string => ($row = LeaveBalance::query()->where(['employee_id' => $who->id, 'leave_type_id' => lvType($code)->id, 'year' => 2027])->first()) ? (string) $row->entitled : null);

    expect($grant(2027, '2027-03-15'))->toBe(7)->and($entitled('ANN', $male))->toBe('3.5')->and($entitled('CAS', $male))->toBe('10.0')->and($entitled('SICK', $male))->toBe('14.0')->and($entitled('MAT', $male))->toBeNull()
        ->and($entitled('MAT', $female))->toBe('112.0')->and($entitled('UNP', $male))->toBeNull()->and($grant(2027, '2027-03-15'))->toBe(0)->and($grant(2027, '2027-06-15'))->toBe(2)->and($entitled('ANN', $male))->toBe('7.0');

    $joiner = hrEmployee(['first_name' => 'New', 'gender' => 'male', 'joined_on' => '2027-02-01']);
    $grant(2027, '2027-06-15');
    expect($entitled('CAS', $joiner))->toBe('9.2')->and($entitled('ANN', $joiner))->toBeNull();

    // 2026: 14 days of annual leave, 6 used, so 8 are unused and 5 may carry into 2027.
    $grant(2026, '2026-12-31');
    booking(fn () => LeaveBalance::query()->where(['employee_id' => $male->id, 'leave_type_id' => lvType('ANN')->id, 'year' => 2026])->update(['used' => '6.0']));
    $grant(2027, '2027-06-15');
    expect(booking(fn (): string => (string) LeaveBalance::query()->where(['employee_id' => $male->id, 'leave_type_id' => lvType('ANN')->id, 'year' => 2027])->value('carried_forward')))->toBe('5.0')
        ->and(booking(fn (): string => (string) LeaveBalance::query()->where(['employee_id' => $male->id, 'leave_type_id' => lvType('CAS')->id, 'year' => 2027])->value('carried_forward')))->toBe('0.0');
});

it('asks for leave: counts the days (not holidays or days off), holds them on the balance and waits for approval', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    lvRoster($employee);
    lvBalance($employee, 'CAS');
    booking(fn () => app(SaveHoliday::class)->handle(null, ['name' => 'Staff day', 'holiday_date' => lvDay(12), 'property_id' => bookingIds()['property']]));
    hrRoster([$employee->id], lvDay(13), lvDay(13), null);

    $leave = lvSubmit($employee, 'CAS', 11, 14);

    expect($leave->request_no)->toStartWith('LV-')->and($leave->status)->toBe(LeaveStatus::Pending)->and((string) $leave->days)->toBe('2.0')->and($leave->charged_dates)->toBe([lvDay(11), lvDay(14)])->and($leave->approval_request_id)->not->toBeNull()
        ->and(lvSum($employee, 'CAS', 'pending'))->toBe('2.0')->and(lvSum($employee, 'CAS', 'used'))->toBe('0.0')
        ->and(booking(fn (): bool => Roster::query()->where('employee_id', $employee->id)->whereDate('work_date', lvDay(11))->sole()->leave_request_id === null))->toBeTrue()
        ->and(booking(fn () => app(ApprovalEngine::class)->find((int) $leave->approval_request_id)?->documentType))->toBe('leave_request');
});

it('approves a request: the days are used and the roster shows the leave in place of the shift (done when)', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    $day = lvRoster($employee);
    lvBalance($employee, 'CAS');
    $leave = lvSubmit($employee, 'CAS', 5, 6);

    lvApprove($leave);
    $row = fn (int $ahead): Roster => booking(fn (): Roster => Roster::query()->where('employee_id', $employee->id)->whereDate('work_date', lvDay($ahead))->sole());

    expect(lvFresh($leave)->status)->toBe(LeaveStatus::Approved)->and(lvFresh($leave)->decided_at)->not->toBeNull()->and(lvSum($employee, 'CAS', 'used'))->toBe('2.0')->and(lvSum($employee, 'CAS', 'pending'))->toBe('0.0')
        ->and($row(5)->shift_id)->toBeNull()->and($row(5)->leave_request_id)->toBe($leave->id)->and($row(5)->replaced_shift_id)->toBe($day->id)->and($row(6)->leave_request_id)->toBe($leave->id)->and($row(7)->shift_id)->toBe($day->id)->and($row(7)->leave_request_id)->toBeNull();
});

it('puts leave on days that had no roster, and takes them off again on cancel', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    lvBalance($employee, 'CAS');
    $leave = lvSubmit($employee, 'CAS', 5, 5);
    lvApprove($leave);

    expect(booking(fn (): int => Roster::query()->where('leave_request_id', $leave->id)->count()))->toBe(1);
    booking(fn () => app(CancelLeaveRequest::class)->handle(lvFresh($leave)));
    expect(booking(fn (): int => Roster::query()->count()))->toBe(0)->and(lvSum($employee, 'CAS', 'used'))->toBe('0.0')->and(lvFresh($leave)->status)->toBe(LeaveStatus::Cancelled);
});

it('rejects a request and gives the days back', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    lvRoster($employee);
    lvBalance($employee, 'CAS');
    $leave = lvSubmit($employee, 'CAS', 5, 6);
    booking(fn () => app(ApprovalEngine::class)->reject((int) $leave->approval_request_id, lvUser(DefaultRole::GeneralManager)->id, 'Fully booked that week'));

    expect(lvFresh($leave)->status)->toBe(LeaveStatus::Rejected)->and(lvSum($employee, 'CAS', 'pending'))->toBe('0.0')->and(lvSum($employee, 'CAS', 'used'))->toBe('0.0')
        ->and(booking(fn (): int => Roster::query()->whereNotNull('leave_request_id')->count()))->toBe(0);
});

it('withdraws a pending request, cancels an approved one before it starts, and refuses leave that has started', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    $day = lvRoster($employee);
    lvBalance($employee, 'CAS');
    $cancel = fn (LeaveRequest $leave) => booking(fn () => app(CancelLeaveRequest::class)->handle(lvFresh($leave)));

    $pending = lvSubmit($employee, 'CAS', 5, 6);
    $cancel($pending);
    expect(lvFresh($pending)->status)->toBe(LeaveStatus::Cancelled)->and(lvSum($employee, 'CAS', 'pending'))->toBe('0.0')->and(booking(fn () => app(ApprovalEngine::class)->find((int) $pending->approval_request_id)?->status->value))->toBe('cancelled')
        ->and(fn () => $cancel($pending))->toThrow(HrRuleViolated::class, 'cancelled');

    $approved = lvSubmit($employee, 'CAS', 8, 9);
    lvApprove($approved);
    $cancel($approved);
    expect(lvFresh($approved)->status)->toBe(LeaveStatus::Cancelled)->and(lvSum($employee, 'CAS', 'used'))->toBe('0.0')
        ->and(booking(fn (): bool => Roster::query()->where('employee_id', $employee->id)->whereDate('work_date', lvDay(8))->sole()->shift_id === $day->id))->toBeTrue();

    $started = lvSubmit($employee, 'CAS', 0, 1);
    lvApprove($started);
    expect(fn () => $cancel($started))->toThrow(HrRuleViolated::class, 'has started');
});

it('refuses overlapping leave, too small a balance, a missing balance, and what the policy does not allow', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString(), 'gender' => 'male']);
    $newcomer = hrEmployee(['first_name' => 'New', 'gender' => 'male', 'joined_on' => now()->subMonths(3)->toDateString()]);
    lvBalance($employee, 'CAS', '10.0');
    lvBalance($employee, 'SICK', '1.0');
    lvBalance($employee, 'MAT', '112.0');
    lvBalance($newcomer, 'ANN', '5.0');
    lvSubmit($employee, 'CAS', 5, 6);
    $by = lvUser()->id;
    $try = fn (callable $fn) => $fn();

    expect(fn () => lvSubmit($employee, 'CAS', 6, 7))->toThrow(HrRuleViolated::class, 'overlaps')
        ->and(fn () => lvSubmit($employee, 'SICK', 20, 21))->toThrow(HrRuleViolated::class, 'only 1.0 days of Sick leave left')
        ->and(fn () => lvSubmit($employee, 'ANN', 20, 21))->toThrow(HrRuleViolated::class, 'no Annual leave balance')
        ->and(fn () => lvSubmit($employee, 'MAT', 20, 21))->toThrow(HrRuleViolated::class, 'for female employees')
        ->and(fn () => lvSubmit($newcomer, 'ANN', 20, 21))->toThrow(HrRuleViolated::class, '12 months of service')
        ->and(fn () => lvSubmit($employee, 'CAS', 30, 33))->toThrow(HrRuleViolated::class, 'at most 3 days')
        ->and(fn () => lvSubmit($employee, 'CAS', 30, 31, HalfDay::First))->toThrow(HrRuleViolated::class, 'half day is one date')
        ->and(fn () => lvSubmit($employee, 'CAS', -40, -39))->toThrow(HrRuleViolated::class, '31 days after')
        ->and(fn () => booking(fn () => app(SubmitLeaveRequest::class)->handle(hrFresh($employee), lvType('CAS'), lvDay(31), lvDay(30), null, null, $by)))->toThrow(HrRuleViolated::class, 'before the first')
        ->and(lvSum($employee, 'SICK', 'pending'))->toBe('0.0');

    // Only a day off in the range: nothing to charge.
    lvRoster($employee);
    hrRoster([$employee->id], lvDay(40), lvDay(40), null);
    expect(fn () => lvSubmit($employee, 'CAS', 40, 40))->toThrow(HrRuleViolated::class, 'holiday or a day off');
});

it('charges a half day as half, and counts weekends and days off for a type that says so', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString(), 'gender' => 'female']);
    lvRoster($employee);
    hrRoster([$employee->id], lvDay(11), lvDay(11), null);
    lvBalance($employee, 'CAS');
    lvBalance($employee, 'MAT', '112.0');

    $half = lvSubmit($employee, 'CAS', 5, 5, HalfDay::Second);
    $maternity = lvSubmit($employee, 'MAT', 10, 12);

    expect((string) $half->days)->toBe('0.5')->and($half->half_day)->toBe(HalfDay::Second)->and((string) $maternity->days)->toBe('3.0')->and(lvSum($employee, 'CAS', 'pending'))->toBe('0.5');
});

it('needs the General Manager as well for ten days or more', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    lvBalance($employee, 'ANN', '14.0');
    $leave = lvSubmit($employee, 'ANN', 20, 29);

    lvApprove($leave, DefaultRole::HrManager);
    expect(lvFresh($leave)->status)->toBe(LeaveStatus::Pending)->and((string) $leave->days)->toBe('10.0')->and(lvSum($employee, 'ANN', 'pending'))->toBe('10.0');

    lvApprove($leave, DefaultRole::GeneralManager);
    expect(lvFresh($leave)->status)->toBe(LeaveStatus::Approved)->and(lvSum($employee, 'ANN', 'used'))->toBe('10.0')->and(lvSum($employee, 'ANN', 'pending'))->toBe('0.0');
});

it('leaves approved leave alone when the roster is assigned or cleared, and keeps the original shift to restore', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    $day = lvRoster($employee);
    $evening = hrShift('EVE', '14:00', '22:00');
    lvBalance($employee, 'CAS');
    $leave = lvSubmit($employee, 'CAS', 5, 6);
    lvApprove($leave);

    $written = booking(fn (): int => app(AssignRoster::class)->handle([$employee->id], lvDay(4), lvDay(7), $evening->id));
    $cleared = booking(fn (): int => app(ClearRoster::class)->handle([$employee->id], lvDay(4), lvDay(7)));

    expect($written)->toBe(2)->and($cleared)->toBe(2)->and(booking(fn (): int => Roster::query()->where('leave_request_id', $leave->id)->count()))->toBe(2)
        ->and(booking(fn (): bool => Roster::query()->where('employee_id', $employee->id)->whereDate('work_date', lvDay(5))->sole()->replaced_shift_id === $day->id))->toBeTrue();
});

it('adjusts a balance by hand with a reason, and never below what is used or waiting', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    lvBalance($employee, 'CAS', '3.0');
    lvSubmit($employee, 'CAS', 5, 6);
    $year = (int) now()->format('Y');
    $adjust = fn (string $days, string $reason = 'Compensation for working a holiday') => booking(fn (): LeaveBalance => app(AdjustLeaveBalance::class)->handle(hrFresh($employee), lvType('CAS'), $year, $days, $reason, 1));

    expect((string) $adjust('2')->adjusted)->toBe('2.0')->and($adjust('-1.5')->available())->toBe('1.5')
        ->and(fn () => $adjust('-3'))->toThrow(HrRuleViolated::class, 'less than the days already used')->and(fn () => $adjust('0'))->toThrow(HrRuleViolated::class, 'number of days')
        ->and(fn () => $adjust('1', ' '))->toThrow(HrRuleViolated::class, 'Say why')->and(fn () => booking(fn () => app(AdjustLeaveBalance::class)->handle(hrFresh($employee), lvType('UNP'), $year, '1', 'x', 1)))->toThrow(HrRuleViolated::class, 'no balance');
});

it('shows approved leave in the attendance report as leave, not as absence', function (): void {
    $employee = hrEmployee(['joined_on' => '2025-01-05']);
    $shift = hrShift('DAY');
    hrRoster([$employee->id], '2026-03-02', '2026-03-06', $shift->id);
    lvBalance($employee, 'CAS');
    booking(fn () => LeaveBalance::query()->where('employee_id', $employee->id)->update(['entitled' => '10.0']));

    // Approved leave on the roster (leave in the past is asked for within 31 days; here the rows are put in directly).
    $leave = booking(fn (): LeaveRequest => LeaveRequest::factory()->create(['property_id' => bookingIds()['property'], 'employee_id' => $employee->id, 'leave_type_id' => lvType('CAS')->id, 'from_date' => '2026-03-03', 'to_date' => '2026-03-04', 'days' => '2.0', 'charged_dates' => ['2026-03-03', '2026-03-04'], 'days_by_year' => ['2026' => '2.0'], 'status' => LeaveStatus::Pending]));
    booking(fn () => app(LeaveApprover::class)->approve($leave));
    $report = booking(fn (): array => app(AttendanceReports::class)->monthly(bookingIds()['property'], '2026-03', null, '2026-03-31'));
    $row = collect($report['rows'])->first(fn (array $candidate): bool => str_starts_with($candidate[0], 'EMP-0001'));

    // Scheduled days: 5 days on the roster, 2 of them leave, so 3 scheduled and all 3 absent (nobody punched); the leave column says 2.
    expect(array_slice($row ?? [], 2, 4))->toBe(['3', '0', '3', '2'])->and($report['columns'][5])->toBe('On leave')->and($report['totals'][5])->toBe('2');
});
