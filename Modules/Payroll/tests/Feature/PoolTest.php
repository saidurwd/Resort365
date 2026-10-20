<?php

/*
| Service charge and tips (Step 6.6): working a month's pool out by each rule, who shares, sending it to payroll, the shares on
| the payslips, and the lock once the run is approved. "Done when": a month's collected service charge and tips are distributed
| to eligible staff through payroll.
*/
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Models\Employee;
use Modules\Payroll\Actions\CalculatePayroll;
use Modules\Payroll\Actions\DeletePool;
use Modules\Payroll\Actions\FinalisePool;
use Modules\Payroll\Actions\PreparePool;
use Modules\Payroll\Actions\ReopenPool;
use Modules\Payroll\Actions\SavePoolParticipants;
use Modules\Payroll\Actions\SubmitPayrollRun;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolRule;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\ServiceChargeAllocation;
use Modules\Payroll\Models\ServiceChargePool;

require_once __DIR__.'/../Support/payroll-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    prPrepare();
});

function plPrepare(PoolKind $kind = PoolKind::ServiceCharge, PoolRule $rule = PoolRule::Equal, ?string $percent = null, string $month = '2026-03'): ServiceChargePool
{
    return booking(fn (): ServiceChargePool => app(PreparePool::class)->handle(bookingIds()['property'], $month, $kind, $rule, $percent, 1));
}

/**
 * A pool's shares by employee number.
 *
 * @return array<string, string>
 */
function plShares(ServiceChargePool $pool): array
{
    return booking(fn (): array => ServiceChargeAllocation::query()->where('service_charge_pool_id', $pool->id)->orderBy('employee_no')->get()->mapWithKeys(fn (ServiceChargeAllocation $a): array => [$a->employee_no => (string) $a->amount])->all());
}

function plFresh(ServiceChargePool $pool): ServiceChargePool
{
    return booking(fn (): ServiceChargePool => ServiceChargePool::query()->findOrFail($pool->id));
}

function plParticipant(Employee $employee, bool $eligible, string $points = '1'): void
{
    booking(fn () => app(SavePoolParticipants::class)->handle([['employee_id' => $employee->id, 'eligible' => $eligible, 'points' => $points]]));
}

it('shares a pool equally between those who worked, scaled by the part of the month a new joiner was here', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    $pool = plPrepare();

    // Rahim all month (1.0000), New from the 16th (16 of 31 days = 0.5161); Zed has no salary, so payroll could not pay him.
    expect($pool->status)->toBe(PoolStatus::Calculated)->and((string) $pool->collected)->toBe('100000.00')->and((string) $pool->staff_percent)->toBe('100.00')->and((string) $pool->distributable)->toBe('100000.00')
        ->and($pool->employees_count)->toBe(2)->and(plShares($pool))->toBe(['EMP-0001' => '65958.71', 'EMP-0002' => '34041.29']);
});

it('shares only the staff percent of what was collected', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    $pool = plPrepare(percent: '80');

    expect((string) $pool->distributable)->toBe('80000.00')->and((string) $pool->collected)->toBe('100000.00')->and(plShares($pool))->toBe(['EMP-0001' => '52766.97', 'EMP-0002' => '27233.03']);
});

it('shares by points, and leaves out someone who does not take part', function (): void {
    [$rahim, $new] = prMarch();
    prCollect(['service_charge' => '100000.00']);
    plParticipant($rahim, true, '2');

    expect(plShares(plPrepare(rule: PoolRule::Points)))->toBe(['EMP-0001' => '79488.10', 'EMP-0002' => '20511.90']);

    plParticipant($rahim, false);
    $pool = plPrepare(rule: PoolRule::Points);

    expect(plShares($pool))->toBe(['EMP-0002' => '100000.00'])->and($pool->employees_count)->toBe(1);

    plParticipant($rahim, true, '0');
    expect(plShares(plPrepare(rule: PoolRule::Points)))->toBe(['EMP-0002' => '100000.00']);
});

it('shares by the days worked: scheduled days less absences', function (): void {
    prMarch();
    prCollect(['tips' => '9000.00']);
    // Rahim was rostered five days and missed one (4 worked); New had no roster, so no days.
    $pool = plPrepare(PoolKind::Tips, PoolRule::DaysWorked);

    expect(plShares($pool))->toBe(['EMP-0001' => '9000.00'])->and((string) booking(fn () => ServiceChargeAllocation::query()->where('service_charge_pool_id', $pool->id)->value('weight')))->toBe('4.0000');
});

it('refuses a month that has not begun, a bad percent, or money with nobody to share it', function (): void {
    [$rahim, $new] = prMarch();
    prCollect(['service_charge' => '1000.00']);

    expect(fn () => plPrepare(month: now()->addMonths(3)->format('Y-m')))->toThrow(PayrollRuleViolated::class, 'not begun')
        ->and(fn () => plPrepare(month: 'March'))->toThrow(PayrollRuleViolated::class, 'Choose a month')
        ->and(fn () => plPrepare(percent: '120'))->toThrow(PayrollRuleViolated::class, '0 and 100');

    plParticipant($rahim, false);
    plParticipant($new, false);

    expect(fn () => plPrepare())->toThrow(PayrollRuleViolated::class, 'No eligible employee');
});

it('works out again from the ledger until it is sent to payroll, and then it is locked', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    $pool = plPrepare();
    prCollect(['service_charge' => '60000.00']);
    $again = plPrepare();

    expect($again->id)->toBe($pool->id)->and((string) plFresh($pool)->distributable)->toBe('60000.00')->and(booking(fn (): int => ServiceChargeAllocation::query()->count()))->toBe(2);

    booking(fn () => app(FinalisePool::class)->handle(plFresh($pool), 1));

    expect(plFresh($pool)->status)->toBe(PoolStatus::Finalised)->and(fn () => plPrepare())->toThrow(PayrollRuleViolated::class, 'cannot be worked out again')
        ->and(fn () => booking(fn () => app(FinalisePool::class)->handle(plFresh($pool), 1)))->toThrow(PayrollRuleViolated::class, 'worked out');
});

it('will not send an empty pool to payroll', function (): void {
    prMarch();
    prCollect([]);
    $pool = plPrepare();

    expect((string) $pool->distributable)->toBe('0.00')->and(fn () => booking(fn () => app(FinalisePool::class)->handle($pool, 1)))->toThrow(PayrollRuleViolated::class, 'nothing to distribute');
});

it('takes a pool back from payroll, or deletes it, before the run is approved', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    $pool = plPrepare();
    booking(fn () => app(FinalisePool::class)->handle($pool, 1));

    booking(fn () => app(ReopenPool::class)->handle(plFresh($pool)));
    expect(plFresh($pool)->status)->toBe(PoolStatus::Calculated)->and(fn () => booking(fn () => app(ReopenPool::class)->handle(plFresh($pool))))->toThrow(PayrollRuleViolated::class, 'waiting for payroll');

    booking(fn () => app(DeletePool::class)->handle(plFresh($pool)));
    expect(booking(fn (): int => ServiceChargePool::query()->count()))->toBe(0)->and(booking(fn (): int => ServiceChargeAllocation::query()->count()))->toBe(0);
});

it('adds the shares to the payslips when the run is calculated, as taxable earnings', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00', 'tips' => '20000.00']);
    $service = plPrepare();
    $tips = plPrepare(PoolKind::Tips);
    booking(fn () => app(FinalisePool::class)->handle($service, 1));
    booking(fn () => app(FinalisePool::class)->handle($tips, 1));
    $run = prRun();
    $slip = prSlip($run, 'EMP-0001');

    // Rahim's ordinary month, overtime, absence and tax come to a gross of 42,980.77 (see PayrollRunTest); the shares come on top.
    expect(prLines($slip)['SCSHARE'])->toBe('65958.71')->and(prLines($slip)['TIPSHARE'])->toBe('13191.74')
        ->and((string) $slip->gross)->toBe('122131.22')->and((float) $slip->tax)->toBeGreaterThan(416.67)
        ->and(prLines(prSlip($run, 'EMP-0002'))['SCSHARE'])->toBe('34041.29')
        ->and(booking(fn (): int => ServiceChargeAllocation::query()->whereNotNull('payslip_id')->count()))->toBe(4)
        ->and(prFresh($run)->warnings)->toHaveCount(1);
});

it('warns when someone with a share has no payslip', function (): void {
    [, , $zed] = prMarch();
    prCollect(['service_charge' => '1000.00']);
    $pool = plPrepare();
    booking(fn () => ServiceChargeAllocation::factory()->create(['service_charge_pool_id' => $pool->id, 'employee_id' => $zed->id, 'employee_no' => 'EMP-0003', 'employee_name' => 'Zed Uddin', 'amount' => '10.00']));
    booking(fn () => app(FinalisePool::class)->handle($pool, 1));
    $run = prRun();

    expect(implode(' ', prFresh($run)->warnings ?? []))->toContain('Zed Uddin has a share');
});

it('leaves payslips without shares alone when no pool is ready', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    plPrepare();
    $run = prRun();

    expect(prLines(prSlip($run, 'EMP-0001')))->not->toHaveKey('SCSHARE')->and((string) prSlip($run, 'EMP-0001')->gross)->toBe('42980.77');
});

it('distributes the pool when the run is approved, and nothing about it can be changed after that', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    $pool = plPrepare();
    booking(fn () => app(FinalisePool::class)->handle($pool, 1));
    $run = prApprove(prRun());
    $distributed = plFresh($pool);

    expect($distributed->status)->toBe(PoolStatus::Distributed)->and($distributed->payroll_run_id)->toBe($run->id)->and($distributed->distributed_at)->not->toBeNull()
        ->and(fn () => booking(fn () => $distributed->update(['collected' => '1.00'])))->toThrow(PayrollRuleViolated::class, 'cannot be changed')
        ->and(fn () => booking(fn () => $distributed->delete()))->toThrow(PayrollRuleViolated::class, 'cannot be deleted')
        ->and(fn () => booking(fn () => ServiceChargeAllocation::query()->where('service_charge_pool_id', $pool->id)->first()->update(['amount' => '1.00'])))->toThrow(PayrollRuleViolated::class, 'cannot be changed')
        ->and(fn () => booking(fn () => app(DeletePool::class)->handle($distributed)))->toThrow(PayrollRuleViolated::class, 'cannot be deleted')
        ->and(fn () => booking(fn () => app(ReopenPool::class)->handle($distributed)))->toThrow(PayrollRuleViolated::class, 'waiting for payroll')
        ->and(fn () => plPrepare())->toThrow(PayrollRuleViolated::class, 'cannot be worked out again');
});

it('keeps a pool with the run once it has gone for approval', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    $pool = plPrepare();
    booking(fn () => app(FinalisePool::class)->handle($pool, 1));
    $run = prRun();
    booking(fn (): PayrollRun => app(SubmitPayrollRun::class)->handle(prFresh($run), prUser()->id));

    expect(fn () => booking(fn () => app(ReopenPool::class)->handle(plFresh($pool))))->toThrow(PayrollRuleViolated::class, 'waiting for approval');

    // A pool for the same month made after the run was sent cannot join it.
    prCollect(['tips' => '500.00']);
    $tips = plPrepare(PoolKind::Tips);

    expect(fn () => booking(fn () => app(FinalisePool::class)->handle($tips, 1)))->toThrow(PayrollRuleViolated::class, 'can no longer take this pool');
});

it('shares a recalculated run the new pool', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    $pool = plPrepare();
    booking(fn () => app(FinalisePool::class)->handle($pool, 1));
    $run = prRun();

    booking(fn () => app(ReopenPool::class)->handle(plFresh($pool)));
    prCollect(['service_charge' => '50000.00']);
    plPrepare();
    booking(fn () => app(FinalisePool::class)->handle(plFresh($pool), 1));
    booking(fn (): PayrollRun => app(CalculatePayroll::class)->handle(prFresh($run)));

    expect(prLines(prSlip($run, 'EMP-0001'))['SCSHARE'])->toBe('32979.35')->and(booking(fn (): int => Payslip::query()->count()))->toBe(2);
});
