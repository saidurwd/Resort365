<?php

/*
| Employees (Step 6.1): creating and changing them, encrypted identity and bank details, managers, the user link and the
| directory contract. "Done when": the demo has about 60 employees across departments (see the DemoSeeder test).
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\HR\Actions\LinkEmployeeUser;
use Modules\HR\Actions\SaveEmployee;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\DTOs\EmployeeSummary;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Grade;

require_once __DIR__.'/../Support/hr-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

it('adds an employee with the next number, a joined entry in the history, and a status from the probation', function (): void {
    $permanent = hrEmployee();
    $probation = hrEmployee(['first_name' => 'Nusrat', 'probation_ends_on' => '2026-04-05']);

    expect($permanent->employee_no)->toBe('EMP-0001')->and($permanent->status)->toBe(EmployeeStatus::Active)->and($probation->employee_no)->toBe('EMP-0002')->and($probation->status)->toBe(EmployeeStatus::OnProbation)
        ->and(hrFresh($permanent)->histories->pluck('event')->all())->toBe([HistoryEvent::Joined])->and(hrFresh($permanent)->histories->first()->effective_on->toDateString())->toBe('2026-01-05');
});

it('keeps the national ID, passport and bank account encrypted, hidden, and unchanged when left empty', function (): void {
    $employee = hrEmployee(['national_id' => '1990 1234 5678', 'passport_no' => 'BX1234567', 'bank_account_no' => '0012-345-678', 'bank_name' => 'City Bank']);
    $row = DB::table('employees')->where('id', $employee->id)->first();

    expect($row?->national_id)->not->toContain('1234')->and($row?->passport_no)->not->toContain('BX12')->and($row?->bank_account_no)->not->toContain('0012')->and($row?->national_id_hash)->toHaveLength(64)
        ->and(hrFresh($employee)->national_id)->toBe('1990 1234 5678')->and(hrFresh($employee)->toArray())->not->toHaveKeys(['national_id', 'passport_no', 'bank_account_no']);

    $edited = booking(fn (): Employee => app(SaveEmployee::class)->handle(hrFresh($employee), [
        'first_name' => 'Rahim', 'last_name' => 'Uddin', 'department_id' => $employee->department_id, 'designation_id' => $employee->designation_id, 'employment_type' => 'permanent',
        'joined_on' => '2026-01-05', 'national_id' => '', 'passport_no' => '', 'bank_account_no' => '', 'phone' => '01711000000',
    ]));

    expect(hrFresh($edited)->national_id)->toBe('1990 1234 5678')->and(hrFresh($edited)->bank_account_no)->toBe('0012-345-678')->and(hrFresh($edited)->phone)->toBe('01711000000')
        ->and(Employee::mask('1990 1234 5678'))->toBe('••••••••••5678');
});

it('refuses a national ID that another employee has, an inactive designation, and a probation that ends before the start', function (): void {
    hrEmployee(['national_id' => '1990-1234-5678']);
    $inactive = booking(fn (): Designation => Designation::factory()->create(['name' => 'Retired role', 'is_active' => false]));

    expect(fn () => hrEmployee(['first_name' => 'Other', 'national_id' => '1990 1234 5678']))->toThrow(HrRuleViolated::class, 'already has this national ID')
        ->and(fn () => hrEmployee(['first_name' => 'Other', 'designation_id' => $inactive->id]))->toThrow(HrRuleViolated::class, 'active designation')
        ->and(fn () => hrEmployee(['first_name' => 'Other', 'probation_ends_on' => '2025-12-31']))->toThrow(HrRuleViolated::class, 'cannot end before')
        ->and(booking(fn (): int => Employee::query()->count()))->toBe(1);
});

it('lets an employee report to a manager, but never in a circle', function (): void {
    $boss = hrEmployee(['first_name' => 'Boss']);
    $lead = hrEmployee(['first_name' => 'Lead', 'manager_id' => $boss->id]);
    $staff = hrEmployee(['first_name' => 'Staff', 'manager_id' => $lead->id]);
    $change = fn (Employee $employee, int $managerId) => booking(fn (): Employee => app(SaveEmployee::class)->handle(hrFresh($employee), [
        'first_name' => $employee->first_name, 'department_id' => $employee->department_id, 'designation_id' => $employee->designation_id, 'employment_type' => 'permanent', 'joined_on' => '2026-01-05', 'manager_id' => $managerId,
    ]));

    expect(fn () => $change($boss, $staff->id))->toThrow(HrRuleViolated::class, 'cannot report to the employee')->and(fn () => $change($lead, $lead->id))->toThrow(HrRuleViolated::class, 'cannot report to the employee')
        ->and(hrFresh($staff)->manager?->first_name)->toBe('Lead')->and(fn () => hrEmployee(['first_name' => 'X', 'manager_id' => 999999]))->toThrow(HrRuleViolated::class, 'manager');
});

it('links an employee to a user account, once per account', function (): void {
    $first = hrEmployee();
    $second = hrEmployee(['first_name' => 'Nusrat']);
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent);
    $link = fn (Employee $employee, ?int $userId) => booking(fn (): Employee => app(LinkEmployeeUser::class)->handle(hrFresh($employee), $userId));

    expect($link($first, $user->id)->user_id)->toBe($user->id)->and(fn () => $link($second, $user->id))->toThrow(HrRuleViolated::class, 'already linked')
        ->and(fn () => $link($second, 999999))->toThrow(HrRuleViolated::class, 'users')->and($link($first, null)->user_id)->toBeNull();
});

it('answers other modules through the employee directory', function (): void {
    $basics = hrBasics();
    $grade = booking(fn (): Grade => Grade::factory()->create(['code' => 'G5']));
    $one = hrEmployee(['first_name' => 'Rahim', 'grade_id' => $grade->id]);
    hrEmployee(['first_name' => 'Nusrat', 'last_name' => 'Jahan']);
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent);
    booking(fn () => app(LinkEmployeeUser::class)->handle(hrFresh($one), $user->id));
    $directory = fn () => app(EmployeeDirectory::class);

    expect(booking(fn () => $directory()->find($one->id)?->employeeNo))->toBe('EMP-0001')->and(booking(fn () => $directory()->forUser($user->id)?->name))->toBe('Rahim Uddin')
        ->and(booking(fn () => array_map(fn (EmployeeSummary $e) => $e->name, $directory()->search('nus'))))->toBe(['Nusrat Jahan'])->and(booking(fn () => $directory()->search('EMP-0001')[0]->designation))->toBe('Receptionist')
        ->and(booking(fn () => count($directory()->forProperty(bookingIds()['property'], $basics['department']))))->toBe(2)->and(booking(fn () => $directory()->find(999999)))->toBeNull();
});
