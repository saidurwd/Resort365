<?php

use App\Http\Middleware\SetCurrentProperty;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\TaxCategory;
use Modules\IAM\Models\User;
use Modules\Property\Models\Property;
use Modules\Property\Models\RoomType;
use Modules\Rates\Enums\MealPlan;
use Modules\Rates\Models\Rate;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Models\Season;

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
function sunriseRun(Closure $callback): mixed
{
    app(PropertyContext::class)->clear();

    return app(TenantContext::class)->run(tenant('sunrise'), $callback);
}

function seasonManager(string $code = 'CXB'): User
{
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);
    $propertyId = sunriseRun(fn (): int => Property::query()->where('code', $code)->value('id'));
    DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'property_id' => $propertyId]);
    withSession([SetCurrentProperty::SESSION_KEY => $propertyId]);

    return $user;
}

it('creates a season with several periods and refuses overlapping ones', function (): void {
    actingAs(seasonManager());

    post(tenantUrl('sunrise', '/rates/seasons'), ['name' => 'Peak', 'priority' => 30, 'color' => 'danger', 'periods' => [
        ['start_date' => '2026-12-15', 'end_date' => '2027-01-31'], ['start_date' => '2026-04-10', 'end_date' => '2026-04-14'], ['start_date' => '', 'end_date' => ''],
    ]])->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/rates/seasons'));

    $season = sunriseRun(fn (): Season => Season::query()->with('periods')->sole());
    expect($season->periods->map(fn ($p) => $p->start_date->toDateString())->all())->toBe(['2026-04-10', '2026-12-15'])
        ->and($season->property_id)->toBe(sunriseRun(fn (): int => Property::query()->where('code', 'CXB')->value('id')));

    post(tenantUrl('sunrise', '/rates/seasons'), ['name' => 'Bad', 'priority' => 1, 'color' => 'info', 'periods' => [
        ['start_date' => '2026-01-01', 'end_date' => '2026-01-10'], ['start_date' => '2026-01-10', 'end_date' => '2026-01-20'],
    ]])->assertSessionHasErrors('periods');
    post(tenantUrl('sunrise', '/rates/seasons'), ['name' => '', 'priority' => 0, 'color' => 'pink', 'periods' => [['start_date' => '2026-02-10', 'end_date' => '2026-02-01']]])
        ->assertSessionHasErrors(['name', 'priority', 'color', 'periods.0.end_date']);

    get(tenantUrl('sunrise', '/rates/seasons'))->assertOk()->assertSee('15 Dec 2026');
    put(tenantUrl('sunrise', '/rates/seasons/'.$season->id), ['name' => 'Peak', 'priority' => 35, 'color' => 'danger', 'periods' => [['start_date' => '2026-12-20', 'end_date' => '2027-01-10']]])
        ->assertSessionHasNoErrors();
    get(tenantUrl('sunrise', '/rates/seasons/'.$season->id.'/edit'))->assertOk()->assertSee('20 Dec 2026 – 10 Jan 2027');
});

it('deletes a season with its rates', function (): void {
    actingAs(seasonManager());
    $season = sunriseRun(function (): Season {
        $season = Season::factory()->create(['property_id' => Property::query()->where('code', 'CXB')->value('id')]);
        Rate::factory()->create(['property_id' => $season->property_id, 'season_id' => $season->id]);
        Rate::factory()->create(['property_id' => $season->property_id]);

        return $season;
    });

    delete(tenantUrl('sunrise', '/rates/seasons/'.$season->id))->assertRedirect(tenantUrl('sunrise', '/rates/seasons'));
    expect(sunriseRun(fn (): int => Season::query()->count()))->toBe(0)->and(sunriseRun(fn (): int => Rate::query()->count()))->toBe(1);
});

it('creates a rate plan with meals, taxes and channels', function (): void {
    $room = sunriseRun(fn (): TaxCategory => TaxCategory::factory()->create(['code' => 'ROOM', 'name' => 'Room']));
    actingAs(seasonManager());

    get(tenantUrl('sunrise', '/rates/rate-plans/create'))->assertOk()->assertSee('Room');
    post(tenantUrl('sunrise', '/rates/rate-plans'), [
        'code' => 'bb', 'name' => 'Bed & Breakfast', 'meal_plan' => 'CP', 'meal_adult_amount' => '800', 'meal_child_amount' => '400',
        'tax_category_id' => $room->id, 'channels' => ['front_desk', 'online'], 'valid_from' => '2026-10-01',
    ])->assertSessionHasNoErrors();

    $plan = sunriseRun(fn (): RatePlan => RatePlan::query()->sole());
    expect($plan->code)->toBe('BB')->and($plan->meal_plan)->toBe(MealPlan::CP)->and($plan->meal_adult_amount)->toBe('800.00')
        ->and($plan->tax_category_id)->toBe($room->id)->and($plan->prices_include_tax)->toBeFalse()->and($plan->is_refundable)->toBeTrue();

    get(tenantUrl('sunrise', '/rates/rate-plans'))->assertOk()->assertSee('Bed & Breakfast')->assertSee('Room');
});

it('validates rate plans: unique code per property, own tenant\'s tax category, channels', function (): void {
    sunriseRun(fn () => RatePlan::factory()->create(['property_id' => Property::query()->where('code', 'CXB')->value('id'), 'code' => 'RO']));
    $other = withDefaultRoles(Tenant::factory()->create(['slug' => 'other']));
    $foreign = app(TenantContext::class)->run($other, fn (): TaxCategory => TaxCategory::factory()->create());
    actingAs(seasonManager());

    post(tenantUrl('sunrise', '/rates/rate-plans'), ['code' => 'ro', 'name' => 'x', 'meal_plan' => 'XX', 'tax_category_id' => $foreign->id, 'channels' => [],
        'valid_from' => '2026-10-10', 'valid_to' => '2026-10-01'])
        ->assertSessionHasErrors(['code', 'meal_plan', 'tax_category_id', 'channels', 'valid_to']);

    // The same code is fine in another property.
    actingAs(seasonManager('SYL'));
    post(tenantUrl('sunrise', '/rates/rate-plans'), ['code' => 'RO', 'name' => 'Room Only', 'meal_plan' => 'EP', 'channels' => ['front_desk']])->assertSessionHasNoErrors();
});

it('saves and clears rates on the rate sheet without duplicates', function (): void {
    actingAs(seasonManager());
    [$plan, $key] = sunriseRun(function (): array {
        $propertyId = Property::query()->where('code', 'CXB')->value('id');
        $type = RoomType::factory()->create(['property_id' => $propertyId]);

        return [RatePlan::factory()->create(['property_id' => $propertyId]), 'room_type:'.$type->id];
    });

    put(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/rates"), ['rates' => [$key => ['every' => ['amount' => '6000'], 'weekend' => ['amount' => '7000']]]])->assertSessionHasNoErrors();
    put(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/rates"), ['rates' => [$key => ['every' => ['amount' => '6500', 'extra_adult_amount' => '1200'], 'weekend' => ['amount' => '']]]])
        ->assertSessionHasNoErrors();

    $rates = sunriseRun(fn () => Rate::query()->get());
    expect($rates)->toHaveCount(1)->and($rates[0]->amount)->toBe('6500.00')->and($rates[0]->extra_adult_amount)->toBe('1200.00')->and($rates[0]->dow_mask)->toBe(127);

    get(tenantUrl('sunrise', "/rates/rate-plans/{$plan->id}/rates"))->assertOk()->assertSee('6500.00');
});

it('keeps seasons and plans to managers', function (): void {
    $season = sunriseRun(fn (): Season => Season::factory()->create(['property_id' => Property::query()->where('code', 'CXB')->value('id')]));
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::ReservationAgent);
    DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'property_id' => $season->property_id]);
    actingAs($user);

    get(tenantUrl('sunrise', '/rates/seasons'))->assertOk();
    get(tenantUrl('sunrise', '/rates/rate-plans'))->assertOk();
    post(tenantUrl('sunrise', '/rates/seasons'), [])->assertForbidden();
    delete(tenantUrl('sunrise', '/rates/seasons/'.$season->id))->assertForbidden();
    post(tenantUrl('sunrise', '/rates/rate-plans'), [])->assertForbidden();
});
