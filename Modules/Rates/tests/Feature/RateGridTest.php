<?php

/*
| Step 1.3 "Done when": the rate grid shows the correct nightly price for any date.
*/
use App\Http\Middleware\SetCurrentProperty;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\IAM\Models\User;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Property;
use Modules\Property\Models\RoomType;
use Modules\Rates\Models\RateOverride;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Models\RateRestriction;
use Modules\Rates\Models\Season;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

/**
 * Tenant "sunrise" with properties CXB and SYL; in CXB a Deluxe King room type and a Family
 * Villa cottage type, a Peak season (15 Dec 2026 – 31 Jan 2027, priority 30) and a Room Only plan.
 */
beforeEach(function (): void {
    $tenant = withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));

    app(TenantContext::class)->run($tenant, function (): void {
        $cxb = Property::factory()->create(['code' => 'CXB', 'name' => "Sunrise Cox's Bazar"]);
        Property::factory()->create(['code' => 'SYL', 'name' => 'Sunrise Sylhet']);
        RoomType::factory()->create(['property_id' => $cxb->id, 'code' => 'DK', 'name' => 'Deluxe King']);
        CottageType::factory()->create(['property_id' => $cxb->id, 'code' => 'FV', 'name' => 'Family Villa']);
        $peak = Season::factory()->create(['property_id' => $cxb->id, 'name' => 'Peak', 'priority' => 30]);
        $peak->periods()->create(['property_id' => $cxb->id, 'start_date' => '2026-12-15', 'end_date' => '2027-01-31']);
        RatePlan::factory()->create(['property_id' => $cxb->id, 'code' => 'RO', 'name' => 'Room Only']);
    });
});

/**
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function inSunrise(Closure $callback): mixed
{
    app(PropertyContext::class)->clear();

    return app(TenantContext::class)->run(tenant('sunrise'), $callback);
}

function ratesUser(DefaultRole $role, string ...$codes): User
{
    $user = tenantUserAs(tenant('sunrise'), $role);

    foreach ($codes as $code) {
        DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'user_id' => $user->id,
            'property_id' => inSunrise(fn (): int => Property::query()->where('code', $code)->value('id'))]);
    }

    return $user;
}

/**
 * @return array{string, string, RatePlan, Season}
 */
function cxbKeys(): array
{
    return inSunrise(fn (): array => [
        'room_type:'.RoomType::query()->where('code', 'DK')->value('id'),
        'cottage_type:'.CottageType::query()->where('code', 'FV')->value('id'),
        RatePlan::query()->where('code', 'RO')->sole(),
        Season::query()->where('name', 'Peak')->sole(),
    ]);
}

function inCxb(): void
{
    withSession([SetCurrentProperty::SESSION_KEY => inSunrise(fn (): int => Property::query()->where('code', 'CXB')->value('id'))]);
}

/**
 * Price shown in the grid for a type and date (null = no price).
 *
 * @param  TestResponse<Response>  $grid
 */
function gridPrice(TestResponse $grid, string $unitKey, string $date): ?string
{
    preg_match('/<tr data-unit="'.preg_quote($unitKey, '/').'">(.*?)<\/tr>/s', (string) $grid->getContent(), $row);
    preg_match('/data-date="'.$date.'" data-amount="([^"]*)" data-source="([^"]*)"/', $row[1] ?? '', $cell);

    return isset($cell[1]) && $cell[1] !== '' ? $cell[1].'@'.$cell[2] : null;
}

it('shows the correct nightly price for any date: base, weekend, season, season weekend and date price', function (): void {
    [$room, $villa, $plan, $peak] = cxbKeys();
    actingAs(ratesUser(DefaultRole::GeneralManager, 'CXB'));
    inCxb();

    // Base rates, then Peak rates, through the rate sheet.
    put(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/rates"), ['rates' => [
        $room => ['every' => ['amount' => '6000', 'extra_adult_amount' => '1500'], 'weekend' => ['amount' => '7000']],
        $villa => ['every' => ['amount' => '25000'], 'weekend' => ['amount' => '']],
    ]])->assertSessionHasNoErrors();
    put(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/rates?season={$peak->id}"), ['rates' => [
        $room => ['every' => ['amount' => '9500'], 'weekend' => ['amount' => '11000']],
    ]])->assertSessionHasNoErrors();

    // A date price for New Year's Eve and a minimum stay over New Year, through the grid forms.
    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/overrides"), [
        'units' => [$room], 'from' => '2026-12-31', 'to' => '2026-12-31', 'days' => range(1, 7), 'action' => 'set', 'amount' => '15000',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');
    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/restrictions"), [
        'units' => ['*'], 'from' => '2026-12-30', 'to' => '2027-01-01', 'days' => range(1, 7), 'action' => 'set', 'min_stay' => 2, 'all_plans' => '1',
    ])->assertSessionHasNoErrors();

    $before = get(tenantUrl('sunrise', "/rates/grid?plan={$plan->id}&start=2026-12-08"))->assertOk();
    expect(gridPrice($before, $room, '2026-12-09'))->toBe('6000.00@base')   // Wednesday
        ->and(gridPrice($before, $room, '2026-12-11'))->toBe('7000.00@base') // Friday
        ->and(gridPrice($before, $room, '2026-12-14'))->toBe('6000.00@base') // Monday, day before Peak
        ->and(gridPrice($before, $room, '2026-12-15'))->toBe('9500.00@season') // Tuesday, Peak starts
        ->and(gridPrice($before, $room, '2026-12-18'))->toBe('11000.00@season') // Friday in Peak
        ->and(gridPrice($before, $villa, '2026-12-18'))->toBe('25000.00@base'); // no Peak rate for the villa

    $newYear = get(tenantUrl('sunrise', "/rates/grid?plan={$plan->id}&start=2026-12-28"))->assertOk();
    expect(gridPrice($newYear, $room, '2026-12-31'))->toBe('15000.00@override')
        ->and(gridPrice($newYear, $room, '2026-12-30'))->toBe('9500.00@season')
        ->and(gridPrice($newYear, $villa, '2027-01-02'))->toBe('25000.00@base'); // Saturday, every-day rate (no weekend rate)

    preg_match('/<tr data-unit="'.preg_quote($villa, '/').'">(.*?)<\/tr>/s', (string) $newYear->getContent(), $villaRow);
    expect(substr_count($villaRow[1], __('Min :n', ['n' => 2])))->toBe(3);
});

it('clears date prices and restrictions again', function (): void {
    [$room, , $plan] = cxbKeys();
    actingAs(ratesUser(DefaultRole::GeneralManager, 'CXB'));
    inCxb();

    $range = ['units' => ['*'], 'from' => '2026-12-24', 'to' => '2026-12-26', 'days' => range(1, 7)];
    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/overrides"), [...$range, 'action' => 'set', 'amount' => '12000'])->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/restrictions"), [...$range, 'action' => 'set', 'stop_sell' => '1'])->assertSessionHasNoErrors();
    expect(inSunrise(fn (): int => RateOverride::query()->count()))->toBe(6)->and(inSunrise(fn (): int => RateRestriction::query()->count()))->toBe(3);

    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/overrides"), [...$range, 'units' => [$room], 'action' => 'clear'])->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/restrictions"), [...$range, 'action' => 'clear'])->assertSessionHasNoErrors();

    expect(inSunrise(fn (): int => RateOverride::query()->count()))->toBe(3)->and(inSunrise(fn (): int => RateRestriction::query()->count()))->toBe(0);
});

it('applies a date price only on the chosen weekdays', function (): void {
    [$room, , $plan] = cxbKeys();
    actingAs(ratesUser(DefaultRole::GeneralManager, 'CXB'));
    inCxb();

    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/overrides"), [
        'units' => [$room], 'from' => '2026-11-01', 'to' => '2026-11-30', 'days' => [5, 6], 'action' => 'set', 'amount' => '8800',
    ])->assertSessionHasNoErrors();

    expect(inSunrise(fn (): array => RateOverride::query()->orderBy('date')->get()->map(fn ($o) => $o->date->format('D'))->unique()->values()->all()))->toBe(['Fri', 'Sat'])
        ->and(inSunrise(fn (): int => RateOverride::query()->count()))->toBe(8);
});

it('validates the grid forms', function (): void {
    [$room, , $plan] = cxbKeys();
    actingAs(ratesUser(DefaultRole::GeneralManager, 'CXB'));
    inCxb();

    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/overrides"), ['units' => ['room_type:999999'], 'from' => '2026-12-10', 'to' => '2026-12-01', 'days' => [], 'action' => 'set'])
        ->assertSessionHasErrors(['units', 'to', 'days', 'amount']);
    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/overrides"), ['units' => [$room], 'from' => '2026-01-01', 'to' => '2027-06-01', 'days' => [1], 'action' => 'set', 'amount' => '1'])
        ->assertSessionHasErrors('to');
    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/restrictions"), ['units' => ['*'], 'from' => '2026-12-01', 'to' => '2026-12-02', 'days' => [1], 'action' => 'set'])
        ->assertSessionHasErrors('min_stay');
    put(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/rates"), ['rates' => [$room => ['every' => ['amount' => '-5']]]])->assertSessionHasErrors('rates.'.$room.'.every.amount');
    put(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/rates"), ['rates' => ['room_type:999999' => ['every' => ['amount' => '5']]]])->assertSessionHasErrors('rates');

    expect(inSunrise(fn (): int => RateOverride::query()->count()))->toBe(0);
});

it('lets the front desk read the grid but not change prices', function (): void {
    [$room, , $plan] = cxbKeys();
    actingAs(ratesUser(DefaultRole::FrontDeskAgent, 'CXB'));
    inCxb();

    get(tenantUrl('sunrise', '/rates/grid'))->assertOk()->assertSee('Room Only')->assertDontSee(__('Set price'));
    get(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/rates"))->assertOk()->assertDontSee(__('Save rates'));
    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/overrides"), ['units' => [$room], 'from' => '2026-12-01', 'to' => '2026-12-01', 'days' => [1], 'action' => 'set', 'amount' => '1'])
        ->assertForbidden();
    put(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/rates"), [])->assertForbidden();

    actingAs(ratesUser(DefaultRole::Chef, 'CXB'));
    get(tenantUrl('sunrise', '/rates/grid'))->assertForbidden();
});

it('keeps another property\'s rate plans out of reach', function (): void {
    [, , $plan] = cxbKeys();
    actingAs(ratesUser(DefaultRole::GeneralManager, 'SYL'));

    get(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/rates"))->assertNotFound();
    post(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/overrides"), [])->assertNotFound();
    get(tenantUrl('sunrise', '/rates/grid?plan='.$plan->id))->assertOk()->assertDontSee('Room Only');
});
