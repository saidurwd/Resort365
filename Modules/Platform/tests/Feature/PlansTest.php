<?php

/*
| Plans and moving a tenant between them (Step 7.3): the starting plans, saving and deleting, modules switched on and off with the
| plan, and what is over a smaller plan's limits. "Done when": moving a demo tenant to Starter hides Restaurant, Inventory and
| Accounting.
*/
use App\Support\Tenancy\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Actions\ChangeTenantPlan;
use Modules\Platform\Actions\DeletePlan;
use Modules\Platform\Actions\SavePlan;
use Modules\Platform\Actions\SeedDefaultPlans;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\Plan;
use Modules\Platform\Services\ModuleCatalog;
use Modules\Property\Models\Room;

require_once __DIR__.'/../Support/platform-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pfSetup();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function plData(array $overrides = []): array
{
    return ['code' => 'gold', 'name' => 'Gold', 'description' => 'A plan', 'monthly_price' => '99.00', 'yearly_price' => '990.00', 'currency_code' => 'usd', 'max_properties' => 2, 'max_rooms' => 40, 'max_users' => null, 'storage_gb' => 5, 'modules' => ['restaurant'], ...$overrides];
}

function plEnabled(string $module): bool
{
    return booking(fn (): bool => app(ModuleAccess::class)->enabled($module));
}

it('starts with Starter, Professional and Enterprise, and adds them only once', function (): void {
    $starter = pfPlan('starter');
    $professional = pfPlan('professional');
    $enterprise = pfPlan('enterprise');

    expect(Plan::query()->count())->toBe(3)->and(app(SeedDefaultPlans::class)->handle())->toBe(0)
        ->and([$starter->max_properties, $starter->max_rooms, $starter->max_users, $starter->storage_gb])->toBe([1, 20, 5, 2])->and($starter->modules)->toBe(['property', 'guest', 'rates', 'reservation', 'billing', 'frontoffice', 'housekeeping', 'website'])
        ->and([$professional->max_properties, $professional->max_rooms, $professional->max_users, $professional->storage_gb])->toBe([3, 150, 30, 20])->and($professional->modules)->toBe(ModuleCatalog::aliases())
        ->and([$enterprise->max_rooms, $enterprise->storage_gb, $enterprise->api_access])->toBe([null, null, true])->and($starter->monthly_price)->toBe('49.00');
});

it('saves a plan with the modules its modules need, and a limit left empty as no limit', function (): void {
    $plan = app(SavePlan::class)->handle(null, plData());

    expect($plan->code)->toBe('gold')->and($plan->currency_code)->toBe('USD')->and($plan->modules)->toBe(['property', 'guest', 'rates', 'reservation', 'billing', 'restaurant'])->and($plan->max_users)->toBeNull()
        ->and($plan->limit('max_rooms'))->toBe(40)->and($plan->limit('max_users'))->toBeNull()->and($plan->limit('anything'))->toBeNull()->and(pfAudit('plan.'))->toBe(['plan.created']);

    $changed = app(SavePlan::class)->handle($plan, plData(['name' => 'Gold plus', 'modules' => ['payroll'], 'is_active' => false]));

    expect($changed->id)->toBe($plan->id)->and($changed->name)->toBe('Gold plus')->and($changed->modules)->toBe(['property', 'hr', 'payroll'])->and($changed->is_active)->toBeFalse()->and(pfAudit('plan.'))->toBe(['plan.created', 'plan.updated']);
});

it('refuses a plan with a bad code, a code in use, a limit below one or a negative price', function (): void {
    $save = fn (array $overrides) => fn () => app(SavePlan::class)->handle(null, plData($overrides));

    expect($save(['code' => 'X']))->toThrow(PlatformRuleViolated::class, '2 to 30')->and($save(['code' => 'has space']))->toThrow(PlatformRuleViolated::class, '2 to 30')
        ->and($save(['code' => 'starter']))->toThrow(PlatformRuleViolated::class, 'with the code "starter"')->and($save(['max_rooms' => 0]))->toThrow(PlatformRuleViolated::class, 'at least 1')
        ->and($save(['monthly_price' => '-1']))->toThrow(PlatformRuleViolated::class, 'not negative');
    expect(app(SavePlan::class)->handle(pfPlan('starter'), plData(['code' => 'starter', 'name' => 'Starter']))->code)->toBe('starter');
});

it('deletes a plan nobody is on, and refuses one in use', function (): void {
    $gold = app(SavePlan::class)->handle(null, plData());
    pfMove('starter');

    expect(fn () => app(DeletePlan::class)->handle(pfPlan('starter')))->toThrow(PlatformRuleViolated::class, '1 tenant is on Starter');
    app(DeletePlan::class)->handle($gold);

    expect(Plan::query()->where('code', 'gold')->exists())->toBeFalse()->and(pfAudit('plan.'))->toBe(['plan.created', 'plan.deleted']);
});

it('hides Restaurant, Inventory and Accounting when the tenant moves to Starter (done when)', function (): void {
    expect(plEnabled('restaurant'))->toBeTrue()->and(plEnabled('accounting'))->toBeTrue();

    pfMove('starter');

    expect(plEnabled('restaurant'))->toBeFalse()->and(plEnabled('inventory'))->toBeFalse()->and(plEnabled('accounting'))->toBeFalse()->and(plEnabled('procurement'))->toBeFalse()->and(plEnabled('payroll'))->toBeFalse()
        ->and(plEnabled('reservation'))->toBeTrue()->and(plEnabled('frontoffice'))->toBeTrue()->and(plEnabled('property'))->toBeTrue()->and(plEnabled('iam'))->toBeTrue()->and(plEnabled('reports'))->toBeTrue()
        ->and(tenant('sunrise')->plan_id)->toBe(pfPlan('starter')->id)->and(pfAudit('tenant.'))->toBe(['tenant.plan_changed']);

    pfMove('professional');

    expect(plEnabled('restaurant'))->toBeTrue()->and(plEnabled('accounting'))->toBeTrue();
});

it('switches every module back on and removes the limits when the tenant has no plan', function (): void {
    pfMove('starter');

    app(ChangeTenantPlan::class)->handle(tenant('sunrise'), null);

    expect(plEnabled('restaurant'))->toBeTrue()->and(tenant('sunrise')->plan_id)->toBeNull();
});

it('lists what a smaller plan would leave over its limits, and removes nothing', function (): void {
    $small = app(SavePlan::class)->handle(null, plData(['code' => 'tiny', 'name' => 'Tiny', 'max_rooms' => 5, 'max_properties' => 1]));
    $over = app(ChangeTenantPlan::class)->handle(tenant('sunrise'), $small);

    expect($over)->toHaveCount(1)->and($over[0])->toContain('Rooms: 8 in use, the plan allows 5')->and(booking(fn (): int => Room::query()->count()))->toBe(8);
});

it('will not move a tenant to an inactive plan, but lets it stay on one', function (): void {
    $retired = app(SavePlan::class)->handle(null, plData(['code' => 'retired', 'name' => 'Retired', 'is_active' => false]));

    expect(fn () => app(ChangeTenantPlan::class)->handle(tenant('sunrise'), $retired))->toThrow(PlatformRuleViolated::class, 'inactive');

    tenant('sunrise')->forceFill(['plan_id' => $retired->id])->save();

    expect(app(ChangeTenantPlan::class)->handle(tenant('sunrise')->fresh(), $retired))->toBeArray();
});
