<?php

/*
| The leave screens (Step 6.3): leave types, requests, a request's page, My leave, balances, the calendar, and who may use them.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Actions\LinkEmployeeUser;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/hr-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    lvPrepare();
});

it('manages leave types and their policies through the screens', function (): void {
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/leave-types'))->assertOk()->assertSeeHtml('data-leave-type="ANN"')->assertSeeHtml('data-leave-type="MAT"');
    get(tenantUrl('sunrise', '/hr/leave-types/create'))->assertOk()->assertSeeHtml('data-leave-type-form');
    post(tenantUrl('sunrise', '/hr/leave-types'), ['code' => '', 'annual_days' => '-1'])->assertSessionHasErrors(['code', 'name', 'annual_days']);
    post(tenantUrl('sunrise', '/hr/leave-types'), ['code' => 'study', 'name' => 'Study leave', 'annual_days' => '5', 'accrual' => 'yearly', 'has_balance' => '1', 'is_paid' => '1', 'employment_types' => ['permanent']])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/hr/leave-types'), ['code' => 'STUDY', 'name' => 'Again', 'annual_days' => '5'])->assertSessionHas('error');
    $study = lvType('STUDY');
    get(tenantUrl('sunrise', "/hr/leave-types/{$study->id}/edit"))->assertOk()->assertSee('Study leave');
    put(tenantUrl('sunrise', "/hr/leave-types/{$study->id}"), ['code' => 'STUDY', 'name' => 'Study', 'annual_days' => '6', 'accrual' => 'monthly', 'has_balance' => '1'])->assertSessionHas('success');
    delete(tenantUrl('sunrise', '/hr/leave-types/'.lvType('CAS')->id))->assertSessionHas('success');
    delete(tenantUrl('sunrise', "/hr/leave-types/{$study->id}"))->assertSessionHas('success');
    expect(booking(fn (): bool => LeaveType::query()->where('code', 'STUDY')->exists()))->toBeFalse();
});

it('asks for leave for an employee as HR, shows the request with its approval and balance, and cancels it', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    lvBalance($employee, 'CAS');
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/leave/create'))->assertOk()->assertSeeHtml('data-leave-form');
    post(tenantUrl('sunrise', '/hr/leave'), ['leave_type_id' => lvType('CAS')->id, 'from_date' => lvDay(5), 'to_date' => lvDay(4)])->assertSessionHasErrors('to_date');
    post(tenantUrl('sunrise', '/hr/leave'), ['leave_type_id' => lvType('CAS')->id, 'from_date' => lvDay(5), 'to_date' => lvDay(6)])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/hr/leave'), ['employee_id' => $employee->id, 'leave_type_id' => lvType('CAS')->id, 'from_date' => lvDay(5), 'to_date' => lvDay(6), 'reason' => 'Wedding'])->assertSessionHas('success');

    $leave = booking(fn (): LeaveRequest => LeaveRequest::query()->sole());
    get(tenantUrl('sunrise', "/hr/leave/{$leave->id}"))->assertOk()->assertSeeHtml('data-leave-summary')->assertSeeHtml('data-approval')->assertSeeHtml('data-balances')->assertSee('Wedding')->assertSeeHtml('data-cancel-leave');
    get(tenantUrl('sunrise', '/hr/leave'))->assertOk()->assertSeeHtml('data-leave="'.$leave->request_no.'"');
    get(tenantUrl('sunrise', '/hr/leave?status=approved'))->assertOk()->assertDontSee($leave->request_no);
    post(tenantUrl('sunrise', "/hr/leave/{$leave->id}/cancel"))->assertSessionHas('success');
    post(tenantUrl('sunrise', "/hr/leave/{$leave->id}/cancel"))->assertSessionHas('error');
    expect(lvSum($employee, 'CAS', 'pending'))->toBe('0.0');
});

it('lets an employee see and ask for their own leave, and nobody else\'s', function (): void {
    $mine = hrEmployee(['first_name' => 'Nusrat', 'joined_on' => now()->subYears(2)->toDateString()]);
    $other = hrEmployee(['first_name' => 'Rahim', 'joined_on' => now()->subYears(2)->toDateString()]);
    lvBalance($mine, 'CAS', '8.0');
    lvBalance($other, 'CAS');
    $theirs = lvSubmit($other, 'CAS', 15, 16);
    $user = staffUser(DefaultRole::FrontDeskAgent);
    booking(fn () => app(LinkEmployeeUser::class)->handle(hrFresh($mine), $user->id));

    get(tenantUrl('sunrise', '/hr/leave/mine'))->assertOk()->assertSeeHtml('data-my-balances')->assertSeeHtml('data-available="CAS"')->assertSee('8.0');
    get(tenantUrl('sunrise', '/hr/leave/create'))->assertOk()->assertDontSee('Type a name or number');
    post(tenantUrl('sunrise', '/hr/leave'), ['employee_id' => $other->id, 'leave_type_id' => lvType('CAS')->id, 'from_date' => lvDay(5), 'to_date' => lvDay(5)])->assertSessionHas('success');

    $own = booking(fn (): LeaveRequest => LeaveRequest::query()->where('employee_id', $mine->id)->sole());
    get(tenantUrl('sunrise', '/hr/leave/mine'))->assertOk()->assertSeeHtml('data-my-requests')->assertSee($own->request_no);
    get(tenantUrl('sunrise', "/hr/leave/{$own->id}"))->assertOk()->assertSeeHtml('data-cancel-leave');
    get(tenantUrl('sunrise', "/hr/leave/{$theirs->id}"))->assertForbidden();
    post(tenantUrl('sunrise', "/hr/leave/{$theirs->id}/cancel"))->assertForbidden();
    get(tenantUrl('sunrise', '/hr/leave'))->assertForbidden();
    get(tenantUrl('sunrise', '/hr/leave/balances'))->assertForbidden();
    get(tenantUrl('sunrise', '/hr/leave/calendar'))->assertForbidden();
    get(tenantUrl('sunrise', '/hr/leave-types'))->assertForbidden();
    post(tenantUrl('sunrise', '/hr/leave/balances'), ['employee_id' => $mine->id, 'leave_type_id' => lvType('CAS')->id, 'year' => now()->year, 'days' => '5', 'reason' => 'x'])->assertForbidden();
    post(tenantUrl('sunrise', "/hr/leave/{$own->id}/cancel"))->assertSessionHas('success');
});

it('tells a user with no employee record that HR must link them', function (): void {
    staffUser(DefaultRole::FrontDeskAgent);

    get(tenantUrl('sunrise', '/hr/leave/mine'))->assertOk()->assertSee('not linked to an employee record');
    post(tenantUrl('sunrise', '/hr/leave'), ['leave_type_id' => lvType('CAS')->id, 'from_date' => lvDay(5), 'to_date' => lvDay(5)])->assertSessionHas('error');
});

it('shows balances with the parts, adjusts them, and shows the leave calendar', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    lvBalance($employee, 'CAS', '10.0');
    $leave = lvSubmit($employee, 'CAS', 5, 6);
    lvApprove($leave);
    $pending = lvSubmit($employee, 'CAS', 10, 10);
    staffUser(DefaultRole::HrManager);
    $year = now()->format('Y');
    $month = now()->addDays(5)->format('Y-m');

    get(tenantUrl('sunrise', "/hr/leave/balances?year={$year}"))->assertOk()->assertSeeHtml('data-balance-row="EMP-0001"')->assertSeeHtml('data-adjust="EMP-0001"');
    post(tenantUrl('sunrise', '/hr/leave/balances'), ['employee_id' => $employee->id, 'leave_type_id' => lvType('CAS')->id, 'year' => $year, 'days' => '2', 'reason' => ''])->assertSessionHasErrors('reason');
    post(tenantUrl('sunrise', '/hr/leave/balances'), ['employee_id' => $employee->id, 'leave_type_id' => lvType('CAS')->id, 'year' => $year, 'days' => '2', 'reason' => 'Worked on a holiday'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/hr/leave/balances'), ['employee_id' => $employee->id, 'leave_type_id' => lvType('CAS')->id, 'year' => $year, 'days' => '-50', 'reason' => 'Too much'])->assertSessionHas('error');
    get(tenantUrl('sunrise', "/hr/leave/calendar?month={$month}"))->assertOk()->assertSeeHtml('data-leave-calendar')->assertSeeHtml('data-calendar-row="EMP-0001"')->assertSeeHtml('data-cell="'.lvDay(5).'"');
    expect(lvSum($employee, 'CAS', 'adjusted'))->toBe('2.0')->and($pending->status->value)->toBe('pending');
});

it('shows approved leave on the roster grid and the attendance screen', function (): void {
    $employee = hrEmployee(['joined_on' => now()->subYears(2)->toDateString()]);
    lvRoster($employee);
    lvBalance($employee, 'CAS');
    $leave = lvSubmit($employee, 'CAS', 5, 5);
    lvApprove($leave);
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/roster?from='.lvDay(3).'&days=14'))->assertOk()->assertSeeHtml('data-leave-cell');
    get(tenantUrl('sunrise', '/hr/attendance?date='.lvDay(5)))->assertOk()->assertSeeHtml('data-on-leave');
    expect(booking(fn (): int => LeaveBalance::query()->count()))->toBeGreaterThan(0);
});
