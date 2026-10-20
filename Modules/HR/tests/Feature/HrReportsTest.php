<?php

/*
| HR reports (Step 6.6): headcount, the attendance summary by department, and leave balances; on screen and as CSV.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Actions\RecordAttendance;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Models\Employee;
use Modules\HR\Services\AttendanceReports;
use Modules\HR\Services\HrReports;

use function Pest\Laravel\get;

require_once __DIR__.'/../Support/hr-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

it('counts the people employed on the last day, by employment type, with who joined and left', function (): void {
    hrEmployee(['first_name' => 'Old', 'joined_on' => '2025-01-05']);
    hrEmployee(['first_name' => 'Casual', 'joined_on' => '2025-06-01', 'employment_type' => 'contract']);
    hrEmployee(['first_name' => 'New', 'joined_on' => '2026-03-10']);
    $gone = hrEmployee(['first_name' => 'Gone', 'joined_on' => '2025-01-05']);
    booking(fn () => Employee::query()->whereKey($gone->id)->update(['exited_on' => '2026-03-20']));
    $late = hrEmployee(['first_name' => 'Late', 'joined_on' => '2026-04-02']);

    $report = booking(fn (): array => app(HrReports::class)->headcount(bookingIds()['property'], '2026-03-01', '2026-03-31'));

    // On 31 March: Old, Casual and New work here; Gone left on the 20th; Late has not joined. One joined and one left in March.
    expect($report['totals'])->toBe(['Total', '3', '2', '1', '0', '0', '1', '1'])->and($report['columns'])->toContain('Permanent')->and($report['rows'])->toHaveCount(1)
        ->and($late->id)->not->toBe($gone->id);
});

it('adds the month\'s attendance up by department', function (): void {
    $shift = hrShift('DAY', '09:00', '17:00', ['break_minutes' => 30, 'grace_minutes' => 10, 'overtime_after_minutes' => 15]);
    $one = hrEmployee(['first_name' => 'One']);
    $two = hrEmployee(['first_name' => 'Two']);
    hrRoster([$one->id, $two->id], '2026-03-02', '2026-03-03', $shift->id);
    $record = fn (Employee $employee, string $date, ?string $in, ?string $out, ?AttendanceStatus $status = null) => booking(fn () => app(RecordAttendance::class)->handle(hrFresh($employee), $date, $in, $out, $status, null, 1));
    $record($one, '2026-03-02', '09:00', '19:00');
    $record($one, '2026-03-03', '09:00', '17:00');
    $record($two, '2026-03-02', '09:00', '17:00');
    // Two has no record on the 3rd: an absence.

    $summary = booking(fn (): array => app(AttendanceReports::class)->byDepartment(bookingIds()['property'], '2026-03', '2026-03-31'));
    $monthly = booking(fn (): array => app(AttendanceReports::class)->monthly(bookingIds()['property'], '2026-03', null, '2026-03-31'));

    // 4 scheduled days, 3 present, 1 absent, 2 hours of overtime: 75% attendance.
    expect($summary['totals'])->toBe(['Total', '2', '4', '3', '1', '0', '75.0%', '0', '2:00', '24:30'])->and($summary['rows'])->toHaveCount(1)->and($monthly['rows'])->toHaveCount(2);
});

it('adds the leave balances up by department and leave type', function (): void {
    lvPrepare();
    $employee = hrEmployee();
    lvBalance($employee, 'CAS', '14.0');

    $report = booking(fn (): array => app(HrReports::class)->leaveBalances(bookingIds()['property'], (int) now()->format('Y')));

    expect($report['rows'])->not->toBeEmpty()->and($report['totals'][3])->toBe('14.0')->and($report['totals'][8])->toBe('14.0');
});

it('shows the reports on screen and as CSV to HR, and keeps them from others', function (): void {
    hrEmployee();
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/reports'))->assertOk()->assertSeeHtml('data-report="headcount"');
    get(tenantUrl('sunrise', '/hr/reports/headcount?from=2026-01-01&to=2026-03-31'))->assertOk()->assertSee('Headcount on 2026-03-31');
    get(tenantUrl('sunrise', '/hr/reports/headcount?from=2026-04-01&to=2026-03-31'))->assertSessionHasErrors('to');
    get(tenantUrl('sunrise', '/hr/reports/attendance-summary?month=2026-03'))->assertOk();
    get(tenantUrl('sunrise', '/hr/reports/leave-balances?year=2026'))->assertOk();
    $csv = get(tenantUrl('sunrise', '/hr/reports/headcount?to=2026-03-31&export=csv'));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('Department');

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/hr/reports'))->assertForbidden();
    get(tenantUrl('sunrise', '/hr/reports/headcount'))->assertForbidden();
});
