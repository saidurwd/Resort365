<?php

use App\Http\Middleware\SetCurrentProperty;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Activity;
use Modules\IAM\Models\User;
use Modules\Property\Models\Property;
use Modules\Property\Models\RoomType;
use Modules\Rates\Enums\CancellationChargeType;
use Modules\Rates\Enums\DiscountType;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\Promotion;
use Modules\Rates\Models\RatePlan;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $tenant = withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    app(TenantContext::class)->run($tenant, function (): void {
        Property::factory()->create(['code' => 'CXB']);
        Property::factory()->create(['code' => 'SYL']);
    });
});

/**
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function policiesRun(Closure $callback): mixed
{
    app(PropertyContext::class)->clear();

    return app(TenantContext::class)->run(tenant('sunrise'), $callback);
}

function propertyIdOf(string $code): int
{
    return policiesRun(fn (): int => Property::query()->where('code', $code)->value('id'));
}

function policyUser(DefaultRole $role = DefaultRole::GeneralManager, string $code = 'CXB'): User
{
    $user = tenantUserAs(tenant('sunrise'), $role);
    DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'property_id' => propertyIdOf($code)]);
    withSession([SetCurrentProperty::SESSION_KEY => propertyIdOf($code)]);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function flexibleInput(array $overrides = []): array
{
    return [
        'name' => 'Flexible', 'is_default' => '1', 'no_show_charge_type' => 'percent_of_deposit', 'no_show_charge_value' => '100',
        'rules' => [
            ['days_before_from' => 15, 'days_before_to' => '', 'charge_type' => 'percent_of_deposit', 'charge_value' => '0'],
            ['days_before_from' => 7, 'days_before_to' => 14, 'charge_type' => 'percent_of_deposit', 'charge_value' => '50'],
            ['days_before_from' => 0, 'days_before_to' => 6, 'charge_type' => 'percent_of_deposit', 'charge_value' => '100'],
            ['days_before_from' => '', 'days_before_to' => '', 'charge_type' => 'fixed', 'charge_value' => ''],
        ],
        ...$overrides,
    ];
}

it('creates deposit policies, with one default per property', function (): void {
    actingAs(policyUser());

    post(tenantUrl('sunrise', '/rates/deposit-policies'), ['name' => 'Standard', 'type' => 'percentage', 'default_percent' => '30', 'min_percent' => '20',
        'due_within_minutes' => 30, 'balance_due_rule' => 'at_check_in', 'is_default' => '1'])->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/rates/policies'));
    post(tenantUrl('sunrise', '/rates/deposit-policies'), ['name' => 'Groups', 'type' => 'percentage', 'default_percent' => '50',
        'due_within_minutes' => 1440, 'balance_due_rule' => 'days_before_arrival', 'balance_due_days' => 7, 'is_default' => '1'])->assertSessionHasNoErrors();

    expect(policiesRun(fn (): array => DepositPolicy::query()->orderBy('id')->pluck('is_default', 'name')->all()))->toBe(['Standard' => false, 'Groups' => true])
        ->and(policiesRun(fn (): int => DepositPolicy::query()->where('name', 'Groups')->value('property_id')))->toBe(propertyIdOf('CXB'));

    get(tenantUrl('sunrise', '/rates/policies'))->assertOk()->assertSee('Groups')->assertSee(__('negotiable per booking'));
});

it('validates deposit policies', function (): void {
    actingAs(policyUser());

    post(tenantUrl('sunrise', '/rates/deposit-policies'), ['name' => '', 'type' => 'bitcoin', 'default_percent' => '150', 'due_within_minutes' => 0, 'balance_due_rule' => 'never'])
        ->assertSessionHasErrors(['name', 'type', 'default_percent', 'due_within_minutes', 'balance_due_rule']);
    post(tenantUrl('sunrise', '/rates/deposit-policies'), ['name' => 'x', 'type' => 'percentage', 'default_percent' => '10', 'min_percent' => '20', 'due_within_minutes' => 30, 'balance_due_rule' => 'at_check_in'])
        ->assertSessionHasErrors('default_percent');
    post(tenantUrl('sunrise', '/rates/deposit-policies'), ['name' => 'x', 'type' => 'fixed_amount', 'default_percent' => '0', 'due_within_minutes' => 30, 'balance_due_rule' => 'days_before_arrival'])
        ->assertSessionHasErrors(['fixed_amount', 'balance_due_days']);
});

it('creates a tiered cancellation policy and records tier changes', function (): void {
    actingAs(policyUser());

    post(tenantUrl('sunrise', '/rates/cancellation-policies'), flexibleInput())->assertSessionHasNoErrors();
    $policy = policiesRun(fn (): CancellationPolicy => CancellationPolicy::query()->with('rules')->sole());

    expect($policy->rules->map(fn ($rule) => [$rule->days_before_from, $rule->days_before_to, $rule->charge_value])->all())
        ->toBe([[15, null, '0.00'], [7, 14, '50.00'], [0, 6, '100.00']])
        ->and($policy->no_show_charge_type)->toBe(CancellationChargeType::PercentOfDeposit);

    put(tenantUrl('sunrise', "/rates/cancellation-policies/{$policy->id}"), flexibleInput(['rules' => [
        ['days_before_from' => 0, 'days_before_to' => '', 'charge_type' => 'nights', 'charge_value' => '1'],
    ]]))->assertSessionHasNoErrors();

    expect(policiesRun(fn (): bool => Activity::query()->where('subject_type', 'cancellation_policy')->where('properties->attributes->tiers', 'like', '%1 night%')->exists()))->toBeTrue();
    get(tenantUrl('sunrise', '/rates/policies'))->assertOk()->assertSee('0 or more days before arrival')->assertSee('1 night');
});

it('refuses overlapping tiers and percentages above 100', function (): void {
    actingAs(policyUser());

    post(tenantUrl('sunrise', '/rates/cancellation-policies'), flexibleInput(['rules' => [
        ['days_before_from' => 7, 'days_before_to' => 14, 'charge_type' => 'percent_of_total', 'charge_value' => '50'],
        ['days_before_from' => 10, 'days_before_to' => '', 'charge_type' => 'percent_of_total', 'charge_value' => '0'],
    ]]))->assertSessionHasErrors(['rules' => __('The tiers must not overlap.')]);
    post(tenantUrl('sunrise', '/rates/cancellation-policies'), flexibleInput(['rules' => [
        ['days_before_from' => 0, 'days_before_to' => '', 'charge_type' => 'percent_of_total', 'charge_value' => '120'],
    ]]))->assertSessionHasErrors('rules');
    post(tenantUrl('sunrise', '/rates/cancellation-policies'), flexibleInput(['rules' => []]))->assertSessionHasErrors('rules');

    expect(policiesRun(fn (): int => CancellationPolicy::query()->count()))->toBe(0);
});

it('links policies to a rate plan, and keeps a policy in use from being deleted', function (): void {
    actingAs(policyUser());
    [$deposit, $flexible, $foreign] = policiesRun(fn (): array => [
        DepositPolicy::factory()->create(['property_id' => propertyIdOf('CXB')]),
        CancellationPolicy::factory()->create(['property_id' => propertyIdOf('CXB')]),
        CancellationPolicy::factory()->create(['property_id' => propertyIdOf('SYL')]),
    ]);

    post(tenantUrl('sunrise', '/rates/rate-plans'), ['code' => 'BB', 'name' => 'Bed & Breakfast', 'meal_plan' => 'CP', 'channels' => ['front_desk'],
        'deposit_policy_id' => $deposit->id, 'cancellation_policy_id' => $foreign->id])->assertSessionHasErrors('cancellation_policy_id');
    post(tenantUrl('sunrise', '/rates/rate-plans'), ['code' => 'BB', 'name' => 'Bed & Breakfast', 'meal_plan' => 'CP', 'channels' => ['front_desk'],
        'deposit_policy_id' => $deposit->id, 'cancellation_policy_id' => $flexible->id])->assertSessionHasNoErrors();

    $plan = policiesRun(fn (): RatePlan => RatePlan::query()->sole());
    expect($plan->deposit_policy_id)->toBe($deposit->id)->and($plan->cancellation_policy_id)->toBe($flexible->id);

    delete(tenantUrl('sunrise', "/rates/cancellation-policies/{$flexible->id}"))->assertSessionHas('error');
    delete(tenantUrl('sunrise', "/rates/deposit-policies/{$deposit->id}"))->assertSessionHas('error');
    get(tenantUrl('sunrise', '/rates/rate-plans'))->assertOk()->assertSee($flexible->name);
});

it('tries out the policies: the §6.5 deposit and the fee for each tier', function (): void {
    actingAs(policyUser());
    post(tenantUrl('sunrise', '/rates/deposit-policies'), ['name' => 'Standard', 'type' => 'percentage', 'default_percent' => '30', 'due_within_minutes' => 30,
        'balance_due_rule' => 'at_check_in', 'is_default' => '1'])->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/rates/cancellation-policies'), flexibleInput())->assertSessionHasNoErrors();

    $arrival = now()->addDays(40)->toDateString();
    $try = fn (array $query) => get(tenantUrl('sunrise', '/rates/policies?'.http_build_query(['total' => '64515.00', 'nights' => 3, 'arrival' => $arrival, ...$query])))->assertOk();

    $try([])->assertSeeInOrder([__('Deposit (:percent%)', ['percent' => '30']), '19,354.50', '45,160.50']);
    $try(['cancel_on' => now()->addDays(30)->toDateString()])->assertSeeInOrder([__('Cancellation fee'), '9,677.25', __('Refund of the deposit'), '9,677.25']);
    $try(['cancel_on' => now()->addDays(38)->toDateString()])->assertSeeInOrder([__('Cancellation fee'), '19,354.50']);
    $try(['no_show' => '1'])->assertSeeInOrder([__('No-show fee'), '19,354.50']);
    $try(['percent' => '50'])->assertSee('32,257.50');
});

it('creates promotions with codes, scope and conditions', function (): void {
    actingAs(policyUser());
    [$plan, $type, $foreignPlan] = policiesRun(fn (): array => [
        RatePlan::factory()->create(['property_id' => propertyIdOf('CXB')]),
        RoomType::factory()->create(['property_id' => propertyIdOf('CXB')]),
        RatePlan::factory()->create(['property_id' => propertyIdOf('SYL')]),
    ]);

    post(tenantUrl('sunrise', '/rates/promotions'), ['code' => 'monsoon20', 'name' => 'Monsoon offer', 'discount_type' => 'percent', 'discount_value' => '20',
        'stay_from' => '2026-06-01', 'stay_to' => '2026-08-31', 'rate_plan_ids' => [$plan->id], 'unit_keys' => ['room_type:'.$type->id]])->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/rates/promotions'), ['name' => 'Long stay', 'discount_type' => 'percent', 'discount_value' => '10', 'min_nights' => 7])->assertSessionHasNoErrors();

    $monsoon = policiesRun(fn (): Promotion => Promotion::query()->where('name', 'Monsoon offer')->sole());
    expect($monsoon->code)->toBe('MONSOON20')
        ->and($monsoon->rate_plan_ids)->toBe([$plan->id])
        ->and($monsoon->unit_keys)->toBe(['room_type:'.$type->id])
        ->and(policiesRun(fn () => Promotion::query()->where('name', 'Long stay')->sole()->terms()->code))->toBeNull()
        ->and(policiesRun(fn () => Promotion::query()->where('name', 'Long stay')->sole()->rate_plan_ids))->toBeNull();

    post(tenantUrl('sunrise', '/rates/promotions'), ['code' => 'MONSOON20', 'name' => 'x', 'discount_type' => 'percent', 'discount_value' => '150',
        'stay_from' => '2026-08-01', 'stay_to' => '2026-07-01', 'rate_plan_ids' => [$foreignPlan->id], 'unit_keys' => ['cottage_type:999999']])
        ->assertSessionHasErrors(['code', 'discount_value', 'stay_to', 'rate_plan_ids.0', 'unit_keys']);

    get(tenantUrl('sunrise', '/rates/promotions'))->assertOk()->assertSee('MONSOON20')->assertSee(__('Automatic'))->assertSee('min 7 nights');
    put(tenantUrl('sunrise', "/rates/promotions/{$monsoon->id}"), ['code' => 'MONSOON20', 'name' => 'Monsoon offer', 'discount_type' => 'fixed_per_night', 'discount_value' => '1500', 'is_active' => '0'])
        ->assertSessionHasNoErrors();
    expect(policiesRun(fn (): Promotion => $monsoon->fresh() ?? throw new RuntimeException('missing'))->discount_type)->toBe(DiscountType::FixedPerNight);

    delete(tenantUrl('sunrise', "/rates/promotions/{$monsoon->id}"))->assertRedirect(tenantUrl('sunrise', '/rates/promotions'));
    expect(policiesRun(fn (): int => Promotion::query()->count()))->toBe(1);
});

it('lets the front desk read policies and promotions but not change them', function (): void {
    $policy = policiesRun(fn (): DepositPolicy => DepositPolicy::factory()->create(['property_id' => propertyIdOf('CXB')]));
    actingAs(policyUser(DefaultRole::FrontDeskAgent));

    get(tenantUrl('sunrise', '/rates/policies'))->assertOk()->assertDontSee(__('New deposit policy'));
    get(tenantUrl('sunrise', '/rates/promotions'))->assertOk();
    post(tenantUrl('sunrise', '/rates/deposit-policies'), [])->assertForbidden();
    put(tenantUrl('sunrise', "/rates/deposit-policies/{$policy->id}"), [])->assertForbidden();
    post(tenantUrl('sunrise', '/rates/promotions'), [])->assertForbidden();

    actingAs(policyUser(DefaultRole::Chef));
    get(tenantUrl('sunrise', '/rates/policies'))->assertForbidden();
});

it('keeps another property\'s policies out of reach', function (): void {
    $policy = policiesRun(fn (): CancellationPolicy => CancellationPolicy::factory()->create(['property_id' => propertyIdOf('CXB')]));
    actingAs(policyUser(code: 'SYL'));

    get(tenantUrl('sunrise', "/rates/cancellation-policies/{$policy->id}/edit"))->assertNotFound();
    get(tenantUrl('sunrise', '/rates/policies'))->assertOk()->assertDontSee($policy->name);
});
