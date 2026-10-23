<?php

/*
| The shift, roster, attendance and holiday screens (Step 6.2) and who may use them.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Holiday;
use Modules\HR\Models\Roster;
use Modules\HR\Models\Shift;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/hr-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

it('manages shifts through the screens, refusing a clash and deleting one in use', function (): void {
    $used = hrShift('DAY');
    hrRoster([hrEmployee()->id], '2026-03-02', '2026-03-02', $used->id);
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/shifts'))->assertOk()->assertSeeHtml('data-shift="DAY"');
    get(tenantUrl('sunrise', '/hr/shifts/create'))->assertOk()->assertSeeHtml('data-shift-form');
    post(tenantUrl('sunrise', '/hr/shifts'), ['code' => '', 'starts_at' => '9am'])->assertSessionHasErrors(['code', 'name', 'starts_at', 'ends_at']);
    post(tenantUrl('sunrise', '/hr/shifts'), ['code' => 'ngt', 'name' => 'Night', 'starts_at' => '22:00', 'ends_at' => '06:00', 'break_minutes' => '30', 'grace_minutes' => '10', 'overtime_after_minutes' => '15', 'color' => 'danger'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/hr/shifts'), ['code' => 'NGT', 'name' => 'Again', 'starts_at' => '22:00', 'ends_at' => '06:00'])->assertSessionHas('error');
    $night = booking(fn (): Shift => Shift::query()->where('code', 'NGT')->firstOrFail());
    get(tenantUrl('sunrise', "/hr/shifts/{$night->id}/edit"))->assertOk();
    put(tenantUrl('sunrise', "/hr/shifts/{$night->id}"), ['code' => 'NGT', 'name' => 'Night watch', 'starts_at' => '22:00', 'ends_at' => '07:00'])->assertSessionHas('success');
    delete(tenantUrl('sunrise', "/hr/shifts/{$used->id}"))->assertSessionHas('error');
    delete(tenantUrl('sunrise', "/hr/shifts/{$night->id}"))->assertSessionHas('success');
    expect(booking(fn (): bool => Shift::query()->whereKey($night->id)->exists()))->toBeFalse();
});

it('manages holidays and lists a year including the repeating ones', function (): void {
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/holidays?year=2026'))->assertOk()->assertSeeHtml('data-holiday-years');
    post(tenantUrl('sunrise', '/hr/holidays'), ['name' => '', 'holiday_date' => 'soon'])->assertSessionHasErrors(['name', 'holiday_date']);
    post(tenantUrl('sunrise', '/hr/holidays'), ['name' => 'Victory Day', 'holiday_date' => '2025-12-16', 'is_paid' => '1', 'repeats_yearly' => '1'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/hr/holidays'), ['name' => 'Same day', 'holiday_date' => '2025-12-16'])->assertSessionHas('error');
    get(tenantUrl('sunrise', '/hr/holidays?year=2027'))->assertOk()->assertSeeHtml('data-holiday="2027-12-16"')->assertSee('Every year');
    $holiday = booking(fn (): Holiday => Holiday::query()->sole());
    get(tenantUrl('sunrise', "/hr/holidays/{$holiday->id}/edit"))->assertOk()->assertSeeHtml('data-holiday-form');
    put(tenantUrl('sunrise', "/hr/holidays/{$holiday->id}"), ['name' => 'Victory Day (Bijoy Dibos)', 'holiday_date' => '2025-12-16', 'repeats_yearly' => '1'])->assertSessionHas('success');
    delete(tenantUrl('sunrise', "/hr/holidays/{$holiday->id}"))->assertSessionHas('success');
    expect(booking(fn (): int => Holiday::query()->count()))->toBe(0);
});

it('shows the roster grid and assigns, copies and clears it from the form', function (): void {
    $day = hrShift('DAY');
    $employee = hrEmployee();
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/roster?from=2026-03-02&days=7'))->assertOk()->assertSeeHtml('data-roster-grid')->assertSeeHtml('data-roster-row="EMP-0001"')->assertSeeHtml('data-assign');
    post(tenantUrl('sunrise', '/hr/roster/assign'), ['employee_ids' => [], 'from' => '2026-03-02', 'to' => '2026-03-08'])->assertSessionHasErrors('employee_ids');
    post(tenantUrl('sunrise', '/hr/roster/assign'), ['employee_ids' => [$employee->id], 'from' => '2026-03-08', 'to' => '2026-03-02'])->assertSessionHasErrors('to');
    post(tenantUrl('sunrise', '/hr/roster/assign'), ['employee_ids' => [$employee->id], 'from' => '2026-03-02', 'to' => '2026-03-08', 'shift_id' => $day->id, 'weekdays' => [1, 2, 3, 4, 0]])->assertSessionHas('success');
    expect(booking(fn (): int => Roster::query()->count()))->toBe(5);
    get(tenantUrl('sunrise', '/hr/roster?from=2026-03-02&days=7'))->assertOk()->assertSee('DAY');

    post(tenantUrl('sunrise', '/hr/roster/copy'), ['employee_ids' => [$employee->id], 'week_start' => '2026-03-01', 'weeks' => '2'])->assertSessionHas('success');
    expect(booking(fn (): int => Roster::query()->count()))->toBe(13);
    post(tenantUrl('sunrise', '/hr/roster/clear'), ['employee_ids' => [$employee->id], 'from' => '2026-03-01', 'to' => '2026-03-31'])->assertSessionHas('success');
    expect(booking(fn (): int => Roster::query()->count()))->toBe(0);
    post(tenantUrl('sunrise', '/hr/roster/nonsense'), [])->assertNotFound();
    post(tenantUrl('sunrise', '/hr/roster/assign'), ['employee_ids' => [$employee->id], 'from' => '2025-01-01', 'to' => '2026-12-31', 'shift_id' => $day->id])->assertSessionHas('error');
});

it('shows a rostered day with no record yet (found by the browser smoke tour, Step 7.6)', function (): void {
    $employee = hrEmployee();
    hrRoster([$employee->id], '2026-03-02', '2026-03-02', hrShift()->id);
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/attendance?date=2026-03-02'))->assertOk()->assertSeeHtml('data-attendance-row="EMP-0001"')->assertSee('No record');
});

it('records, corrects and removes a day from the attendance screen', function (): void {
    $employee = hrEmployee();
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/attendance?date=2026-03-02'))->assertOk()->assertSeeHtml('data-attendance-row="EMP-0001"')->assertSeeHtml('data-record="EMP-0001"');
    post(tenantUrl('sunrise', '/hr/attendance'), ['employee_id' => $employee->id, 'work_date' => '2026-03-02', 'clock_in' => '9am'])->assertSessionHasErrors('clock_in');
    post(tenantUrl('sunrise', '/hr/attendance'), ['employee_id' => $employee->id, 'work_date' => '2026-03-02'])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/hr/attendance'), ['employee_id' => $employee->id, 'work_date' => '2026-03-02', 'clock_in' => '09:00', 'clock_out' => '17:30', 'notes' => 'Stayed late'])->assertSessionHas('success');
    get(tenantUrl('sunrise', '/hr/attendance?date=2026-03-02'))->assertOk()->assertSee('17:30')->assertSee('Day off');

    $record = booking(fn (): Attendance => Attendance::query()->sole());
    expect($record->overtime_minutes)->toBe(510);
    delete(tenantUrl('sunrise', "/hr/attendance/{$record->id}"))->assertSessionHas('success');
    expect(booking(fn (): int => Attendance::query()->count()))->toBe(0);
});

it('imports a CSV from the screen, shows the history, and shows the monthly report and its CSV', function (): void {
    $shift = hrShift('DAY');
    $employee = hrEmployee();
    hrRoster([$employee->id], '2026-03-02', '2026-03-02', $shift->id);
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/attendance/import'))->assertOk()->assertSeeHtml('data-import-form');
    post(tenantUrl('sunrise', '/hr/attendance/import'), [])->assertSessionHasErrors('file');
    post(tenantUrl('sunrise', '/hr/attendance/import'), ['file' => UploadedFile::fake()->createWithContent('bad.csv', "employee,date,in,out\nEMP-0042,2026-03-02,09:00,17:00\n")])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/hr/attendance/import'), ['file' => UploadedFile::fake()->createWithContent('march.csv', "employee,date,in,out\nEMP-0001,2026-03-02,09:40,17:30\n")])->assertSessionHas('success');
    get(tenantUrl('sunrise', '/hr/attendance/import'))->assertOk()->assertSee('march.csv');

    get(tenantUrl('sunrise', '/hr/attendance/report?month=2026-03'))->assertOk()->assertSeeHtml('data-report-table="attendance"')->assertSee('EMP-0001 · Rahim Uddin')->assertSee('March 2026');
    $csv = get(tenantUrl('sunrise', '/hr/attendance/report?month=2026-03&export=csv'));
    $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $content = $csv->streamedContent();
    expect($content)->toContain('EMP-0001');
    expect($content)->toContain('0:30');
});

it('keeps the screens to the people who hold the permissions', function (): void {
    hrEmployee();

    staffUser(DefaultRole::FrontDeskAgent);
    foreach (['/hr/roster', '/hr/attendance', '/hr/attendance/report', '/hr/attendance/import', '/hr/shifts', '/hr/holidays'] as $path) {
        get(tenantUrl('sunrise', $path))->assertForbidden();
    }

    staffUser(DefaultRole::PayrollOfficer);
    get(tenantUrl('sunrise', '/hr/roster'))->assertOk()->assertDontSeeHtml('data-assign');
    get(tenantUrl('sunrise', '/hr/attendance'))->assertOk()->assertDontSeeHtml('data-record="EMP-0001"');
    get(tenantUrl('sunrise', '/hr/attendance/import'))->assertOk();
    get(tenantUrl('sunrise', '/hr/shifts'))->assertOk()->assertDontSee('New shift');
    get(tenantUrl('sunrise', '/hr/holidays'))->assertOk()->assertDontSee('New holiday');
    post(tenantUrl('sunrise', '/hr/roster/assign'), ['employee_ids' => [1], 'from' => '2026-03-02', 'to' => '2026-03-03'])->assertForbidden();
    post(tenantUrl('sunrise', '/hr/attendance'), ['employee_id' => 1, 'work_date' => '2026-03-02', 'clock_in' => '09:00'])->assertForbidden();
    post(tenantUrl('sunrise', '/hr/shifts'), ['code' => 'X', 'name' => 'X', 'starts_at' => '09:00', 'ends_at' => '17:00'])->assertForbidden();
    post(tenantUrl('sunrise', '/hr/holidays'), ['name' => 'X', 'holiday_date' => '2026-03-02'])->assertForbidden();
    expect(booking(fn (): int => Attendance::query()->count()))->toBe(0);
});
