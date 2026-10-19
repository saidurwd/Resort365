<?php

/*
| Attendance (Step 6.2): manual entry, biometric CSV import (punch lists, night shifts, daily rows, all or nothing, safe
| to repeat, manual days kept) and the monthly report. "Done when": a month of imported attendance shows lateness and
| overtime per employee.
*/

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Actions\DeleteAttendance;
use Modules\HR\Actions\ImportAttendance;
use Modules\HR\Actions\RecordAttendance;
use Modules\HR\Actions\SaveHoliday;
use Modules\HR\Enums\AttendanceSource;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\AttendanceImport;
use Modules\HR\Models\Employee;
use Modules\HR\Services\AttendanceReports;

require_once __DIR__.'/../Support/hr-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * @return array{import: AttendanceImport, days: int, skipped: int, rows: int}
 */
function atImport(string $csv, string $name = 'punches.csv'): array
{
    return booking(fn (): array => app(ImportAttendance::class)->handle(bookingIds()['property'], $name, $csv, 1));
}

function atDay(Employee $employee, string $date): ?Attendance
{
    return booking(fn (): ?Attendance => Attendance::query()->where('employee_id', $employee->id)->whereDate('work_date', $date)->first());
}

it('records a day by hand against the rostered shift, and corrects it', function (): void {
    $shift = hrShift('DAY', '09:00', '17:00', ['break_minutes' => 30, 'grace_minutes' => 10, 'overtime_after_minutes' => 15]);
    $employee = hrEmployee();
    hrRoster([$employee->id], '2026-03-02', '2026-03-02', $shift->id);
    $record = fn (?string $in, ?string $out, ?AttendanceStatus $status = null) => booking(fn (): Attendance => app(RecordAttendance::class)->handle(hrFresh($employee), '2026-03-02', $in, $out, $status, 'Held up by the ferry', 1));

    $day = $record('09:25', '18:00');
    expect($day->status)->toBe(AttendanceStatus::Late)->and($day->late_minutes)->toBe(25)->and($day->overtime_minutes)->toBe(60)->and($day->worked_minutes)->toBe(515 - 30)->and($day->source)->toBe(AttendanceSource::Manual)
        ->and($day->shift_id)->toBe($shift->id)->and($day->notes)->toBe('Held up by the ferry');

    $fixed = $record('09:00', '17:00');
    expect($fixed->id)->toBe($day->id)->and($fixed->status)->toBe(AttendanceStatus::Present)->and($fixed->late_minutes)->toBe(0)->and($record(null, null, AttendanceStatus::Absent)->status)->toBe(AttendanceStatus::Absent)
        ->and(atDay($employee, '2026-03-02')?->clock_in)->toBeNull()->and($record('09:00', '12:30', AttendanceStatus::HalfDay)->status)->toBe(AttendanceStatus::HalfDay)
        ->and(booking(fn (): int => Attendance::query()->count()))->toBe(1);
});

it('refuses an entry before joining, after leaving, with nothing in it, or with a status that cannot be set by hand', function (): void {
    $employee = hrEmployee(['joined_on' => '2026-03-01']);
    $record = fn (string $date, ?string $in, ?string $out, ?AttendanceStatus $status = null) => booking(fn () => app(RecordAttendance::class)->handle(hrFresh($employee), $date, $in, $out, $status, null, 1));

    expect(fn () => $record('2026-02-28', '09:00', '17:00'))->toThrow(HrRuleViolated::class, 'joined on')->and(fn () => $record('2026-03-02', null, null))->toThrow(HrRuleViolated::class, 'clock-in or a clock-out')
        ->and(fn () => $record('2026-03-02', '09:00', '17:00', AttendanceStatus::Holiday))->toThrow(HrRuleViolated::class, 'set by hand')->and(booking(fn (): int => Attendance::query()->count()))->toBe(0);
});

it('treats a worked day off or holiday as overtime, and removes a record', function (): void {
    $employee = hrEmployee();
    booking(fn () => app(SaveHoliday::class)->handle(null, ['name' => 'Staff day', 'holiday_date' => '2026-03-04', 'property_id' => bookingIds()['property']]));
    $off = booking(fn (): Attendance => app(RecordAttendance::class)->handle(hrFresh($employee), '2026-03-03', '10:00', '14:00', null, null, 1));
    $holiday = booking(fn (): Attendance => app(RecordAttendance::class)->handle(hrFresh($employee), '2026-03-04', '10:00', '14:00', null, null, 1));

    expect($off->status)->toBe(AttendanceStatus::DayOff)->and($off->overtime_minutes)->toBe(240)->and($holiday->status)->toBe(AttendanceStatus::Holiday)->and($holiday->overtime_minutes)->toBe(240);
    booking(fn () => app(DeleteAttendance::class)->handle($holiday));
    expect(atDay($employee, '2026-03-04'))->toBeNull();
});

it('imports a punch list: first-in and last-out per day, measured against the roster and holidays', function (): void {
    $shift = hrShift('DAY', '09:00', '17:00', ['break_minutes' => 30, 'grace_minutes' => 10, 'overtime_after_minutes' => 15]);
    $rahim = hrEmployee(['first_name' => 'Rahim']);
    $nusrat = hrEmployee(['first_name' => 'Nusrat']);
    hrRoster([$rahim->id, $nusrat->id], '2026-03-02', '2026-03-04', $shift->id);
    booking(fn () => app(SaveHoliday::class)->handle(null, ['name' => 'Staff day', 'holiday_date' => '2026-03-04', 'property_id' => bookingIds()['property']]));

    $result = atImport("Employee No,Date Time\nEMP-0001,2026-03-02 09:02\nEMP-0001,2026-03-02 12:30\nEMP-0001,2026-03-02 17:40\nEMP-0002,2026-03-02 09:30\nEMP-0002,2026-03-02 16:30\nEMP-0001,2026-03-03 09:05\nemp-0001,2026-03-04 10:00\nEMP-0001,2026-03-04 15:00\n");
    $monday = atDay($rahim, '2026-03-02');
    $late = atDay($nusrat, '2026-03-02');

    expect($result['days'])->toBe(4)->and($result['skipped'])->toBe(0)->and($result['rows'])->toBe(8)->and($monday?->status)->toBe(AttendanceStatus::Present)->and($monday?->overtime_minutes)->toBe(40)->and($monday?->source)->toBe(AttendanceSource::Import)
        ->and($late?->status)->toBe(AttendanceStatus::Late)->and($late?->late_minutes)->toBe(30)->and($late?->early_leave_minutes)->toBe(30)
        ->and(atDay($rahim, '2026-03-03')?->status)->toBe(AttendanceStatus::Incomplete)->and(atDay($rahim, '2026-03-04')?->status)->toBe(AttendanceStatus::Holiday)->and(atDay($rahim, '2026-03-04')?->overtime_minutes)->toBe(270)
        ->and($result['import']->filename)->toBe('punches.csv')->and(atDay($rahim, '2026-03-02')?->attendance_import_id)->toBe($result['import']->id);
});

it('puts a night shift\'s morning punch on the day it started', function (): void {
    $night = hrShift('NGT', '22:00', '06:00', ['break_minutes' => 30, 'grace_minutes' => 10, 'overtime_after_minutes' => 15]);
    $guard = hrEmployee(['first_name' => 'Guard']);
    hrRoster([$guard->id], '2026-03-02', '2026-03-03', $night->id);

    atImport("employee,date,time\nEMP-0001,2026-03-02,21:58\nEMP-0001,2026-03-03,06:04\nEMP-0001,2026-03-03,22:20\nEMP-0001,2026-03-04,05:45\n");
    $first = atDay($guard, '2026-03-02');
    $second = atDay($guard, '2026-03-03');

    expect($first?->clock_in)->toBe('21:58:00')->and($first?->clock_out)->toBe('06:04:00')->and($first?->status)->toBe(AttendanceStatus::Present)->and($first?->worked_minutes)->toBe(486 - 30)
        ->and($second?->late_minutes)->toBe(20)->and($second?->early_leave_minutes)->toBe(15)->and(atDay($guard, '2026-03-04'))->toBeNull();
});

it('imports daily rows, skips days before joining and manual days, and replaces earlier imported days when repeated', function (): void {
    $shift = hrShift('DAY');
    $rahim = hrEmployee(['first_name' => 'Rahim']);
    $newcomer = hrEmployee(['first_name' => 'New', 'joined_on' => '2026-03-03']);
    hrRoster([$rahim->id], '2026-03-02', '2026-03-03', $shift->id);
    booking(fn () => app(RecordAttendance::class)->handle(hrFresh($rahim), '2026-03-03', '09:00', '17:00', null, 'Checked by hand', 1));

    $csv = "Employee,Date,Clock In,Clock Out\nEMP-0001,2026-03-02,09:30,17:00\nEMP-0001,2026-03-03,12:00,13:00\nEMP-0002,2026-03-02,09:00,17:00\nEMP-0002,2026-03-03,09:00,17:00\n";
    $first = atImport($csv, 'march.csv');

    expect($first['days'])->toBe(2)->and($first['skipped'])->toBe(2)->and(atDay($rahim, '2026-03-02')?->late_minutes)->toBe(30)->and(atDay($rahim, '2026-03-03')?->notes)->toBe('Checked by hand')
        ->and(atDay($rahim, '2026-03-03')?->source)->toBe(AttendanceSource::Manual)->and(atDay($newcomer, '2026-03-02'))->toBeNull();

    $again = atImport(str_replace('09:30', '09:05', $csv), 'march-again.csv');
    expect($again['days'])->toBe(2)->and(atDay($rahim, '2026-03-02')?->late_minutes)->toBe(0)->and(booking(fn (): int => Attendance::query()->count()))->toBe(3)->and(booking(fn (): int => AttendanceImport::query()->count()))->toBe(2);
});

it('refuses the whole file for an unknown employee, a bad line or an employee of another property, and says which', function (): void {
    hrEmployee();
    hrEmployee(['first_name' => 'Away', 'property_id' => hrSecondProperty()]);
    $before = booking(fn (): int => Attendance::query()->count());

    $attempt = function (string $csv): string {
        try {
            atImport($csv);
        } catch (HrRuleViolated $exception) {
            return $exception->getMessage();
        }

        return '';
    };

    expect($attempt("employee,date,in,out\nEMP-0001,2026-03-02,09:00,17:00\nEMP-0099,2026-03-02,09:00,17:00\n"))->toContain('Nothing was imported')->toContain('Line 3')->toContain('EMP-0099')
        ->and($attempt("employee,date,in,out\nEMP-0002,2026-03-02,09:00,17:00\n"))->toContain('EMP-0002')->and($attempt("employee,date,in,out\nEMP-0001,2026-13-45,09:00,17:00\n"))->toContain('Line 2')
        ->and($attempt('nonsense'))->toContain('header row')->and(booking(fn (): int => Attendance::query()->count()))->toBe($before)->and(booking(fn (): int => AttendanceImport::query()->count()))->toBe(0);
});

it('reports a month per employee: lateness, early leave, overtime, absences and the totals (done when)', function (): void {
    $shift = hrShift('DAY', '09:00', '17:00', ['break_minutes' => 30, 'grace_minutes' => 10, 'overtime_after_minutes' => 15]);
    $rahim = hrEmployee(['first_name' => 'Rahim']);
    $nusrat = hrEmployee(['first_name' => 'Nusrat']);
    hrRoster([$rahim->id, $nusrat->id], '2026-03-02', '2026-03-06', $shift->id);
    booking(fn () => app(SaveHoliday::class)->handle(null, ['name' => 'Staff day', 'holiday_date' => '2026-03-06', 'property_id' => bookingIds()['property']]));

    atImport("employee,date,in,out\nEMP-0001,2026-03-02,09:00,17:00\nEMP-0001,2026-03-03,09:30,17:45\nEMP-0001,2026-03-04,09:00,16:30\nEMP-0001,2026-03-05,09:00,18:00\nEMP-0002,2026-03-02,09:00,17:00\n");
    $report = booking(fn (): array => app(AttendanceReports::class)->monthly(bookingIds()['property'], '2026-03', null, '2026-03-31'));
    $rows = collect($report['rows'])->keyBy(fn (array $row): string => substr($row[0], 0, 8));

    // Rahim: 4 days present, 1 absent day is the holiday (not counted); late 30 on the 3rd, early 30 on the 4th, overtime 45 + 60.
    $rahimRow = collect($report['rows'])->first(fn (array $row): bool => str_starts_with($row[0], 'EMP-0001'));
    $rahimFigures = array_slice($rahimRow ?? [], 2);

    expect($rahimFigures)->toBe(['4', '4', '0', '0', '1', '30', '30', '1:45', '30:45'])
        ->and($rows['EMP-0002'][2])->toBe('4')->and($rows['EMP-0002'][3])->toBe('1')->and($rows['EMP-0002'][4])->toBe('3')
        ->and($report['totals'][6])->toBe('1')->and($report['totals'][4])->toBe('3')->and($report['summary'][2]['value'])->toBe('1:45')->and($report['title'])->toBe('Attendance for March 2026');
});
