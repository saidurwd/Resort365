<?php

/*
| Lifecycle events (Step 6.1): confirmation, transfer, promotion, increment, disciplinary record and exit; each changes
| the employee and writes a history entry.
*/

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\HR\Actions\ConfirmEmployee;
use Modules\HR\Actions\ExitEmployee;
use Modules\HR\Actions\PromoteEmployee;
use Modules\HR\Actions\RecordDisciplinary;
use Modules\HR\Actions\RecordIncrement;
use Modules\HR\Actions\SaveEmployee;
use Modules\HR\Actions\TransferEmployee;
use Modules\HR\Enums\DisciplinaryAction;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\ExitReason;
use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Events\EmployeeExited;
use Modules\HR\Events\EmployeeTransferred;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeHistory;
use Modules\HR\Models\Grade;
use Modules\Property\Models\Department;

require_once __DIR__.'/../Support/hr-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

function lcLast(Employee $employee): EmployeeHistory
{
    return hrFresh($employee)->histories->first();
}

it('confirms an employee after probation, once', function (): void {
    $employee = hrEmployee(['probation_ends_on' => '2026-04-05']);
    $confirmed = booking(fn (): Employee => app(ConfirmEmployee::class)->handle(hrFresh($employee), '2026-04-06', 'Good work', 1));

    expect($confirmed->status)->toBe(EmployeeStatus::Active)->and($confirmed->confirmed_on?->toDateString())->toBe('2026-04-06')->and(lcLast($employee)->event)->toBe(HistoryEvent::Confirmation)->and(lcLast($employee)->notes)->toBe('Good work')
        ->and(fn () => booking(fn () => app(ConfirmEmployee::class)->handle(hrFresh($employee), '2026-04-07', null, 1)))->toThrow(HrRuleViolated::class, 'not on probation')
        ->and(fn () => booking(fn () => app(ConfirmEmployee::class)->handle(hrFresh(hrEmployee(['probation_ends_on' => '2026-04-05'])), '2025-01-01', null, 1)))->toThrow(HrRuleViolated::class, 'before the employee joined');
});

it('promotes to a new designation and grade and keeps the old and new names', function (): void {
    $employee = hrEmployee();
    [$senior, $grade] = booking(fn (): array => [Designation::factory()->create(['name' => 'Front Office Manager']), Grade::factory()->create(['code' => 'M1', 'name' => 'Manager 1'])]);
    booking(fn () => app(PromoteEmployee::class)->handle(hrFresh($employee), $senior->id, $grade->id, '2026-06-01', null, 1));
    $fresh = hrFresh($employee);

    expect($fresh->designation->name)->toBe('Front Office Manager')->and($fresh->grade->name ?? null)->toBe('Manager 1')->and(lcLast($employee)->event)->toBe(HistoryEvent::Promotion)
        ->and(lcLast($employee)->details)->toBe(['designation' => ['Receptionist', 'Front Office Manager'], 'grade' => [null, 'Manager 1']])
        ->and(fn () => booking(fn () => app(PromoteEmployee::class)->handle(hrFresh($employee), $senior->id, $grade->id, '2026-06-02', null, 1)))->toThrow(HrRuleViolated::class, 'already has');
});

it('records an increment that raises the amount, and a disciplinary action that says what happened', function (): void {
    $employee = hrEmployee();
    booking(fn () => app(RecordIncrement::class)->handle(hrFresh($employee), '25000', '27500.5', '2026-07-01', 'Annual', 1));

    expect(lcLast($employee)->details)->toBe(['previous_amount' => '25000.00', 'new_amount' => '27500.50'])
        ->and(fn () => booking(fn () => app(RecordIncrement::class)->handle(hrFresh($employee), '27500', '27500', '2026-07-02', null, 1)))->toThrow(HrRuleViolated::class, 'raise the amount')
        ->and(fn () => booking(fn () => app(RecordIncrement::class)->handle(hrFresh($employee), 'abc', '10', '2026-07-02', null, 1)))->toThrow(HrRuleViolated::class, 'amounts');

    booking(fn () => app(RecordDisciplinary::class)->handle(hrFresh($employee), DisciplinaryAction::WrittenWarning, '2026-08-01', 'Late three times', 1));
    expect(lcLast($employee)->event)->toBe(HistoryEvent::Disciplinary)->and(lcLast($employee)->details)->toBe(['action' => 'written_warning'])
        ->and(fn () => booking(fn () => app(RecordDisciplinary::class)->handle(hrFresh($employee), DisciplinaryAction::VerbalWarning, '2026-08-02', '  ', 1)))->toThrow(HrRuleViolated::class, 'what happened');
});

it('transfers an employee to another property and department, and tells the other modules', function (): void {
    Event::fake([EmployeeTransferred::class]);
    $employee = hrEmployee();
    $dhaka = hrSecondProperty();
    $kitchen = booking(fn (): Department => Department::factory()->create(['code' => 'KIT', 'name' => 'Kitchen']));
    $moved = booking(fn (): Employee => app(TransferEmployee::class)->handle(hrFresh($employee), $dhaka, $kitchen->id, '2026-09-01', 'Opening Dhaka', 1));

    expect($moved->property_id)->toBe($dhaka)->and($moved->department_id)->toBe($kitchen->id)->and(lcLast($employee)->event)->toBe(HistoryEvent::Transfer)
        ->and(lcLast($employee)->details)->toBe(['property' => ["Sunrise Cox's Bazar", 'Sunrise Dhaka'], 'department' => [booking(fn (): string => Department::query()->findOrFail(hrBasics()['department'])->name), 'Kitchen']])
        ->and(fn () => booking(fn () => app(TransferEmployee::class)->handle(hrFresh($employee), $dhaka, null, '2026-09-02', null, 1)))->toThrow(HrRuleViolated::class, 'already works there')
        ->and(fn () => booking(fn () => app(TransferEmployee::class)->handle(hrFresh($employee), 999999, null, '2026-09-02', null, 1)))->toThrow(HrRuleViolated::class, 'property');
    Event::assertDispatched(EmployeeTransferred::class, fn (EmployeeTransferred $event): bool => $event->employeeId === $employee->id && $event->toPropertyId === $dhaka);
});

it('records an exit: status and reason, reports move up to the manager, the history, and nothing more can change', function (): void {
    Event::fake([EmployeeExited::class]);
    $boss = hrEmployee(['first_name' => 'Boss']);
    $lead = hrEmployee(['first_name' => 'Lead', 'manager_id' => $boss->id]);
    $staff = hrEmployee(['first_name' => 'Staff', 'manager_id' => $lead->id]);
    $left = booking(fn (): Employee => app(ExitEmployee::class)->handle(hrFresh($lead), ExitReason::Resignation, '2026-10-01', 'Moving abroad', 1));

    expect($left->status)->toBe(EmployeeStatus::Exited)->and($left->exited_on?->toDateString())->toBe('2026-10-01')->and($left->exit_reason)->toBe(ExitReason::Resignation)->and(hrFresh($staff)->manager_id)->toBe($boss->id)
        ->and(lcLast($lead)->event)->toBe(HistoryEvent::Exit)
        ->and(fn () => booking(fn () => app(PromoteEmployee::class)->handle(hrFresh($lead), hrBasics()['designation'], null, '2026-10-02', null, 1)))->toThrow(HrRuleViolated::class, 'has left')
        ->and(fn () => booking(fn () => app(SaveEmployee::class)->handle(hrFresh($lead), ['first_name' => 'Lead', 'department_id' => hrBasics()['department'], 'designation_id' => hrBasics()['designation'], 'employment_type' => 'permanent', 'joined_on' => '2026-01-05'])))->toThrow(HrRuleViolated::class, 'can no longer be changed')
        ->and(fn () => booking(fn () => app(ExitEmployee::class)->handle(hrFresh(hrEmployee(['first_name' => 'New'])), ExitReason::Other, '2025-01-01', null, 1)))->toThrow(HrRuleViolated::class, 'before they joined');
    Event::assertDispatched(EmployeeExited::class, fn (EmployeeExited $event): bool => $event->employeeId === $lead->id);
});

it('never changes or deletes a history entry', function (): void {
    $entry = hrFresh(hrEmployee())->histories->first();

    expect(fn () => booking(fn () => $entry->update(['notes' => 'edited'])))->toThrow(LogicException::class)->and(fn () => booking(fn () => $entry->delete()))->toThrow(LogicException::class);
});
