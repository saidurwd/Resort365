<?php

/*
| The HR screens (Step 6.1): the employee directory, profile, form, lifecycle forms, user link, designations and grades,
| and who may use them.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Grade;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/hr-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function scForm(array $overrides = []): array
{
    $basics = hrBasics();

    return ['first_name' => 'Nusrat', 'last_name' => 'Jahan', 'department_id' => $basics['department'], 'designation_id' => $basics['designation'], 'employment_type' => 'permanent', 'joined_on' => '2026-02-01', ...$overrides];
}

it('lists employees in a server-side table with filters, and a picker search', function (): void {
    hrEmployee(['first_name' => 'Rahim']);
    hrEmployee(['first_name' => 'Nusrat', 'probation_ends_on' => '2026-05-01']);
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/employees'))->assertOk()->assertSeeHtml('data-employee-filters')->assertSeeHtml('data-new-employee');
    get(tenantUrl('sunrise', '/hr/employees/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsTotal', 2)->assertSee('Rahim Uddin');
    get(tenantUrl('sunrise', '/hr/employees/data?draw=1&start=0&length=10&filter=probation'))->assertJsonPath('recordsFiltered', 1)->assertSee('Nusrat');
    get(tenantUrl('sunrise', '/hr/employees/data?draw=1&start=0&length=10&search[value]=EMP-0001'))->assertJsonPath('recordsFiltered', 1);
    get(tenantUrl('sunrise', '/hr/employees/search?q=nus'))->assertOk()->assertJsonPath('0.text', 'EMP-0002 · Nusrat Uddin · Receptionist');
});

it('adds and changes an employee through the form, with validation', function (): void {
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/employees/create'))->assertOk()->assertSeeHtml('data-employee-form')->assertSee('National ID');
    post(tenantUrl('sunrise', '/hr/employees'), ['first_name' => ''])->assertSessionHasErrors(['first_name', 'department_id', 'designation_id', 'employment_type', 'joined_on']);
    post(tenantUrl('sunrise', '/hr/employees'), scForm(['department_id' => 999999]))->assertSessionHasErrors('department_id');
    post(tenantUrl('sunrise', '/hr/employees'), scForm(['national_id' => '1990123456789', 'bank_account_no' => '001122']))->assertSessionHas('success');

    $employee = booking(fn (): Employee => Employee::query()->sole());
    expect($employee->employee_no)->toBe('EMP-0001')->and($employee->national_id)->toBe('1990123456789');
    get(tenantUrl('sunrise', "/hr/employees/{$employee->id}/edit"))->assertOk()->assertSee('•••••••••6789');
    put(tenantUrl('sunrise', "/hr/employees/{$employee->id}"), scForm(['phone' => '01711000000', 'national_id' => '']))->assertSessionHas('success');
    expect(hrFresh($employee)->phone)->toBe('01711000000')->and(hrFresh($employee)->national_id)->toBe('1990123456789');
    post(tenantUrl('sunrise', '/hr/employees'), scForm(['first_name' => 'Other', 'national_id' => '1990-1234-56789']))->assertSessionHas('error');
});

it('shows the national ID, passport and bank account in full only with the sensitive permission', function (): void {
    $employee = hrEmployee(['national_id' => '1990123456789', 'bank_account_no' => '001122334455']);

    staffUser(DefaultRole::GeneralManager);
    get(tenantUrl('sunrise', "/hr/employees/{$employee->id}"))->assertOk()->assertSee('•••••••••6789')->assertDontSee('1990123456789')->assertDontSee('001122334455')->assertSee('you need permission');
    get(tenantUrl('sunrise', "/hr/employees/{$employee->id}/edit"))->assertOk()->assertDontSee('National ID');
    put(tenantUrl('sunrise', "/hr/employees/{$employee->id}"), scForm(['national_id' => '5550000', 'bank_account_no' => '999']))->assertSessionHas('success');
    expect(hrFresh($employee)->national_id)->toBe('1990123456789')->and(hrFresh($employee)->bank_account_no)->toBe('001122334455');

    staffUser(DefaultRole::HrManager);
    get(tenantUrl('sunrise', "/hr/employees/{$employee->id}"))->assertOk()->assertSee('1990123456789')->assertSee('001122334455');
});

it('shows the profile tabs, career history and direct reports', function (): void {
    $boss = hrEmployee(['first_name' => 'Boss']);
    $staff = hrEmployee(['first_name' => 'Staff', 'manager_id' => $boss->id]);
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', "/hr/employees/{$boss->id}"))->assertOk()->assertSeeHtml('data-hash-tabs')->assertSeeHtml('data-history="joined"')->assertSee('Staff Uddin')->assertSeeHtml('data-lifecycle-menu');
    get(tenantUrl('sunrise', "/hr/employees/{$staff->id}"))->assertOk()->assertSeeHtml('data-manager')->assertSee('Boss Uddin');
});

it('runs the lifecycle forms and shows what was refused', function (): void {
    $employee = hrEmployee(['probation_ends_on' => '2026-05-01']);
    $designation = booking(fn (): Designation => Designation::factory()->create(['name' => 'Supervisor']));
    $url = fn (string $event) => tenantUrl('sunrise', "/hr/employees/{$employee->id}/lifecycle/{$event}");
    staffUser(DefaultRole::HrManager);

    post($url('confirm'), ['effective_on' => '2026-05-02'])->assertSessionHas('success');
    post($url('confirm'), ['effective_on' => '2026-05-03'])->assertSessionHas('error');
    post($url('promote'), ['effective_on' => '2026-06-01', 'designation_id' => $designation->id])->assertSessionHas('success');
    post($url('promote'), ['effective_on' => '2026-06-01'])->assertSessionHasErrors('designation_id');
    post($url('increment'), ['effective_on' => '2026-07-01', 'previous_amount' => '25000', 'new_amount' => '28000'])->assertSessionHas('success');
    post($url('disciplinary'), ['effective_on' => '2026-08-01', 'action' => 'verbal_warning'])->assertSessionHasErrors('notes');
    post($url('disciplinary'), ['effective_on' => '2026-08-01', 'action' => 'verbal_warning', 'notes' => 'Late'])->assertSessionHas('success');
    post($url('transfer'), ['effective_on' => '2026-09-01', 'property_id' => hrSecondProperty()])->assertSessionHas('error');
    post($url('exit'), ['effective_on' => '2026-10-01', 'reason' => 'resignation'])->assertSessionHas('success');
    post($url('nonsense'), ['effective_on' => '2026-10-01'])->assertNotFound();

    expect(hrFresh($employee)->histories->pluck('event.value')->all())->toBe(['exit', 'disciplinary', 'increment', 'promotion', 'confirmation', 'joined']);
    get(tenantUrl('sunrise', "/hr/employees/{$employee->id}"))->assertOk()->assertSeeHtml('data-exited-banner')->assertDontSeeHtml('data-lifecycle-menu')->assertDontSeeHtml('data-edit-employee');
});

it('links and unlinks a user from the profile', function (): void {
    $employee = hrEmployee();
    $user = staffUser(DefaultRole::HrManager);

    put(tenantUrl('sunrise', "/hr/employees/{$employee->id}/user"), ['user_id' => $user->id])->assertSessionHas('success');
    get(tenantUrl('sunrise', "/hr/employees/{$employee->id}"))->assertOk()->assertSeeHtml('data-linked-user')->assertSee($user->email);
    put(tenantUrl('sunrise', "/hr/employees/{$employee->id}/user"), ['user_id' => 999999])->assertSessionHasErrors('user_id');
    put(tenantUrl('sunrise', "/hr/employees/{$employee->id}/user"), ['user_id' => ''])->assertSessionHas('success');
    expect(hrFresh($employee)->user_id)->toBeNull();
});

it('keeps employees to the people who hold the permissions', function (): void {
    $employee = hrEmployee();

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/hr/employees'))->assertForbidden();
    get(tenantUrl('sunrise', "/hr/employees/{$employee->id}"))->assertForbidden();
    get(tenantUrl('sunrise', '/hr/designations'))->assertForbidden();

    staffUser(DefaultRole::PayrollOfficer);
    get(tenantUrl('sunrise', "/hr/employees/{$employee->id}"))->assertOk()->assertDontSeeHtml('data-edit-employee')->assertDontSeeHtml('data-lifecycle-menu');
    get(tenantUrl('sunrise', '/hr/employees/create'))->assertForbidden();
    post(tenantUrl('sunrise', '/hr/employees'), scForm())->assertForbidden();
    post(tenantUrl('sunrise', "/hr/employees/{$employee->id}/lifecycle/exit"), ['effective_on' => '2026-10-01', 'reason' => 'resignation'])->assertForbidden();
    put(tenantUrl('sunrise', "/hr/employees/{$employee->id}/user"), ['user_id' => ''])->assertForbidden();
    expect(booking(fn (): int => Employee::query()->count()))->toBe(1);
});

it('keeps an employee of another property out of reach of someone without access to it', function (): void {
    $dhaka = hrSecondProperty();
    $away = hrEmployee(['first_name' => 'Away', 'property_id' => $dhaka]);
    $here = hrEmployee(['first_name' => 'Here']);
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/employees/data?draw=1&start=0&length=10'))->assertJsonPath('recordsTotal', 1)->assertSee('Here Uddin')->assertDontSee('Away');
    get(tenantUrl('sunrise', "/hr/employees/{$away->id}"))->assertNotFound();
    get(tenantUrl('sunrise', "/hr/employees/{$here->id}"))->assertOk();
});

it('manages designations and grades, and refuses to delete ones in use', function (): void {
    $employee = hrEmployee();
    staffUser(DefaultRole::HrManager);

    get(tenantUrl('sunrise', '/hr/designations'))->assertOk()->assertSeeHtml('data-designations')->assertSee('Receptionist');
    post(tenantUrl('sunrise', '/hr/designations'), ['name' => ''])->assertSessionHasErrors('name');
    post(tenantUrl('sunrise', '/hr/designations'), ['name' => 'Chef de partie', 'is_active' => '1'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/hr/designations'), ['name' => 'chef de partie'])->assertSessionHas('error');
    $chef = booking(fn (): Designation => Designation::query()->where('name', 'Chef de partie')->firstOrFail());
    get(tenantUrl('sunrise', "/hr/designations/{$chef->id}/edit"))->assertOk();
    put(tenantUrl('sunrise', "/hr/designations/{$chef->id}"), ['name' => 'Chef', 'is_active' => '0'])->assertSessionHas('success');
    delete(tenantUrl('sunrise', "/hr/designations/{$employee->designation_id}"))->assertSessionHas('error');
    delete(tenantUrl('sunrise', "/hr/designations/{$chef->id}"))->assertSessionHas('success');
    expect(booking(fn (): bool => Designation::query()->whereKey($chef->id)->exists()))->toBeFalse();

    get(tenantUrl('sunrise', '/hr/grades'))->assertOk()->assertSeeHtml('data-grades');
    post(tenantUrl('sunrise', '/hr/grades'), ['code' => 'M1', 'name' => 'Manager 1', 'level' => '5'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/hr/grades'), ['code' => 'm1', 'name' => 'Again', 'level' => '5'])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/hr/grades'), ['code' => 'X', 'name' => 'Bad', 'level' => '0'])->assertSessionHasErrors('level');
    $grade = booking(fn (): Grade => Grade::query()->where('code', 'M1')->firstOrFail());
    put(tenantUrl('sunrise', "/hr/grades/{$grade->id}"), ['code' => 'M1', 'name' => 'Manager I', 'level' => '6'])->assertSessionHas('success');
    booking(fn () => Employee::query()->whereKey($employee->id)->update(['grade_id' => $grade->id]));
    delete(tenantUrl('sunrise', "/hr/grades/{$grade->id}"))->assertSessionHas('error');
    expect(booking(fn (): int => Grade::query()->findOrFail($grade->id)->level))->toBe(6);

    staffUser(DefaultRole::PayrollOfficer);
    get(tenantUrl('sunrise', '/hr/grades'))->assertOk()->assertDontSee('New grade');
    post(tenantUrl('sunrise', '/hr/grades'), ['code' => 'Z', 'name' => 'Z', 'level' => '1'])->assertForbidden();
    delete(tenantUrl('sunrise', "/hr/designations/{$employee->designation_id}"))->assertForbidden();
});
