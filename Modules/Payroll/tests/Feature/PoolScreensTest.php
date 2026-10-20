<?php

/*
| Service charge and tips on screen (Step 6.6): the pools, working one out, sending it to payroll, who shares, and who may use
| them.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Models\PoolParticipant;
use Modules\Payroll\Models\ServiceChargePool;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../Support/payroll-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    prPrepare();
});

function psPool(): ServiceChargePool
{
    return booking(fn (): ServiceChargePool => ServiceChargePool::query()->firstOrFail());
}

it('works a pool out from the screen and shows the shares', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    staffUser(DefaultRole::PayrollOfficer);

    get(tenantUrl('sunrise', '/payroll/pools'))->assertOk()->assertSeeHtml('data-new-pool');
    get(tenantUrl('sunrise', '/payroll/pools/create'))->assertOk()->assertSeeHtml('data-pool-form');
    post(tenantUrl('sunrise', '/payroll/pools'), ['month' => 'March', 'kind' => 'wrong'])->assertSessionHasErrors(['month', 'kind']);
    post(tenantUrl('sunrise', '/payroll/pools'), ['month' => '2026-03', 'kind' => 'service_charge', 'rule' => 'equal', 'staff_percent' => '150'])->assertSessionHasErrors('staff_percent');
    post(tenantUrl('sunrise', '/payroll/pools'), ['month' => '2026-03', 'kind' => 'service_charge', 'rule' => 'equal'])->assertSessionHas('success');
    $pool = psPool();

    get(tenantUrl('sunrise', "/payroll/pools/{$pool->id}"))->assertOk()->assertSee('100,000.00')->assertSee('Rahim Uddin')->assertSee('65,958.71')->assertSee('34,041.29')->assertSeeHtml('data-finalise');
    get(tenantUrl('sunrise', '/payroll/pools'))->assertOk()->assertSeeHtml('data-pool="2026-03-service_charge"');
});

it('works the pool out again, sends it to payroll, takes it back, and deletes it', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    staffUser(DefaultRole::PayrollOfficer);
    post(tenantUrl('sunrise', '/payroll/pools'), ['month' => '2026-03', 'kind' => 'service_charge', 'rule' => 'equal']);
    $pool = psPool();

    prCollect(['service_charge' => '60000.00']);
    post(tenantUrl('sunrise', "/payroll/pools/{$pool->id}/recalculate"))->assertSessionHas('success');
    expect((string) psPool()->distributable)->toBe('60000.00');

    post(tenantUrl('sunrise', "/payroll/pools/{$pool->id}/finalise"))->assertSessionHas('success');
    expect(psPool()->status)->toBe(PoolStatus::Finalised);
    post(tenantUrl('sunrise', "/payroll/pools/{$pool->id}/recalculate"))->assertSessionHas('error');
    delete(tenantUrl('sunrise', "/payroll/pools/{$pool->id}"))->assertSessionHas('success');
    expect(booking(fn (): int => ServiceChargePool::query()->count()))->toBe(0);
});

it('says when the month\'s run is already calculated and needs calculating again', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    $run = prRun();
    staffUser(DefaultRole::PayrollOfficer);
    post(tenantUrl('sunrise', '/payroll/pools'), ['month' => '2026-03', 'kind' => 'service_charge', 'rule' => 'equal']);
    $pool = psPool();

    $response = get(tenantUrl('sunrise', "/payroll/pools/{$pool->id}"));
    $response->assertOk();
    post(tenantUrl('sunrise', "/payroll/pools/{$pool->id}/finalise"))->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'calculate it again'));
    get(tenantUrl('sunrise', "/payroll/pools/{$pool->id}"))->assertSeeHtml('data-waiting')->assertSee($run->run_no);
});

it('lists who shares and saves the points and who is left out', function (): void {
    [$rahim, $new] = prMarch();
    staffUser(DefaultRole::PayrollOfficer);

    get(tenantUrl('sunrise', '/payroll/pools/participants'))->assertOk()->assertSeeHtml('data-participant="EMP-0001"')->assertSeeHtml('data-participant="EMP-0002"');
    post(tenantUrl('sunrise', '/payroll/pools/participants'), [])->assertSessionHasErrors('participants');
    post(tenantUrl('sunrise', '/payroll/pools/participants'), ['participants' => [['employee_id' => $rahim->id, 'eligible' => '1', 'points' => '5000']]])->assertSessionHasErrors('participants.0.points');
    post(tenantUrl('sunrise', '/payroll/pools/participants'), ['participants' => [
        ['employee_id' => $rahim->id, 'eligible' => '1', 'points' => '2'], ['employee_id' => $new->id, 'eligible' => '0', 'points' => '1'],
    ]])->assertSessionHas('success');

    $saved = booking(fn () => PoolParticipant::query()->orderBy('employee_id')->get()->map(fn (PoolParticipant $p): array => [$p->employee_id, $p->is_eligible, (string) $p->points])->all());
    expect($saved)->toBe([[$rahim->id, true, '2.00'], [$new->id, false, '1.00']]);
});

it('lets the accountant look but not change, and keeps everyone else out', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    staffUser(DefaultRole::PayrollOfficer);
    post(tenantUrl('sunrise', '/payroll/pools'), ['month' => '2026-03', 'kind' => 'service_charge', 'rule' => 'equal']);
    $pool = psPool();

    staffUser(DefaultRole::Accountant);
    get(tenantUrl('sunrise', '/payroll/pools'))->assertOk()->assertDontSeeHtml('data-new-pool');
    get(tenantUrl('sunrise', "/payroll/pools/{$pool->id}"))->assertOk()->assertDontSeeHtml('data-finalise');
    get(tenantUrl('sunrise', '/payroll/pools/create'))->assertForbidden();
    post(tenantUrl('sunrise', '/payroll/pools'), ['month' => '2026-03', 'kind' => 'tips'])->assertForbidden();
    post(tenantUrl('sunrise', "/payroll/pools/{$pool->id}/finalise"))->assertForbidden();
    delete(tenantUrl('sunrise', "/payroll/pools/{$pool->id}"))->assertForbidden();

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/payroll/pools'))->assertForbidden();
    get(tenantUrl('sunrise', "/payroll/pools/{$pool->id}"))->assertForbidden();
    get(tenantUrl('sunrise', '/payroll/pools/participants'))->assertForbidden();
});
