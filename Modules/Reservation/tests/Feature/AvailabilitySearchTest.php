<?php

/*
| Step 1.5 "Done when": room booked → cottage not available whole; cottage booked whole → none of
| its rooms; restrictions respected; the prices match the rate grid.
*/
use App\Http\Middleware\SetCurrentProperty;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;
use Modules\IAM\Models\User;
use Modules\Property\Enums\BookingMode;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;
use Modules\Property\Models\RoomType;
use Modules\Rates\Actions\SaveRateSheet;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\NightlyRate;
use Modules\Rates\DTOs\RestrictionSet;
use Modules\Rates\Models\Promotion;
use Modules\Rates\Models\RateOverride;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Models\RateRestriction;
use Modules\Rates\Models\Season;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\DTOs\PricedNight;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Services\PricingService;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

/**
 * Sunrise CXB: a Deluxe King room type (base 2, max 2 adults + 1 child, 3 guests); cottage C07
 * (Family Villa type, rooms 701 and 702) and single-room cottage C01 (room 101), both sold whole
 * or by the room; a Room Only plan with Room taxes (SC 10% + VAT 15% compound): Deluxe King 6,000
 * (weekend 7,000, extras 1,500 / 750), Peak 9,500; Family Villa 15,000 (no Honeymoon rate);
 * a date price of 15,000 on 31 Dec 2026 for Deluxe King.
 */
beforeEach(function (): void {
    $tenant = withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));

    app(TenantContext::class)->run($tenant, function (): void {
        $cxb = Property::factory()->create(['code' => 'CXB']);
        $king = RoomType::factory()->create(['property_id' => $cxb->id, 'code' => 'DK', 'name' => 'Deluxe King', 'base_occupancy' => 2, 'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3]);
        $villaType = CottageType::factory()->create(['property_id' => $cxb->id, 'code' => 'FV', 'name' => 'Family Villa', 'max_occupancy' => 6]);
        $single = CottageType::factory()->create(['property_id' => $cxb->id, 'code' => 'HC', 'name' => 'Honeymoon']);

        $villa = Cottage::factory()->create(['property_id' => $cxb->id, 'cottage_type_id' => $villaType->id, 'code' => 'C07', 'name' => 'Lagoon Villa', 'booking_mode' => BookingMode::Both]);
        $coral = Cottage::factory()->create(['property_id' => $cxb->id, 'cottage_type_id' => $single->id, 'code' => 'C01', 'name' => 'Coral', 'booking_mode' => BookingMode::Both]);
        foreach ([['701', $villa], ['702', $villa], ['101', $coral]] as [$number, $cottage]) {
            Room::factory()->create(['property_id' => $cxb->id, 'cottage_id' => $cottage->id, 'room_type_id' => $king->id, 'number' => $number]);
        }

        $sc = Tax::factory()->create(['code' => 'SC', 'rate' => '10', 'sort_order' => 10]);
        $vat = Tax::factory()->compound()->create(['code' => 'VAT', 'rate' => '15', 'sort_order' => 20]);
        $room = TaxCategory::factory()->create(['code' => 'ROOM']);
        $room->taxes()->attach([$sc->id => ['tenant_id' => $room->tenant_id], $vat->id => ['tenant_id' => $room->tenant_id]]);

        $plan = RatePlan::factory()->create(['property_id' => $cxb->id, 'code' => 'RO', 'name' => 'Room Only', 'tax_category_id' => $room->id,
            'meal_adult_amount' => '0', 'meal_child_amount' => '0', 'valid_from' => null, 'valid_to' => null]);
        $peak = Season::factory()->create(['property_id' => $cxb->id, 'name' => 'Peak', 'priority' => 30]);
        $peak->periods()->create(['property_id' => $cxb->id, 'start_date' => '2026-12-15', 'end_date' => '2027-01-31']);

        SaveRateSheet::make()->handle($plan, null, [
            'room_type:'.$king->id => ['every' => ['amount' => '6000', 'extra_adult_amount' => '1500', 'extra_child_amount' => '750'], 'weekend' => ['amount' => '7000', 'extra_adult_amount' => '1500', 'extra_child_amount' => '750']],
            'cottage_type:'.$villaType->id => ['every' => ['amount' => '15000']],
        ], 48);
        SaveRateSheet::make()->handle($plan, $peak, ['room_type:'.$king->id => ['every' => ['amount' => '9500', 'extra_adult_amount' => '1500', 'extra_child_amount' => '750']]], 48);
        RateOverride::factory()->create(['property_id' => $cxb->id, 'rate_plan_id' => $plan->id, 'rateable_type' => 'room_type', 'rateable_id' => $king->id, 'date' => '2026-12-31', 'amount' => '15000']);
    });
});

/**
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function resv(Closure $callback): mixed
{
    app(PropertyContext::class)->clear();

    return app(TenantContext::class)->run(tenant('sunrise'), $callback);
}

function resvUser(DefaultRole $role = DefaultRole::FrontDeskAgent): User
{
    $user = tenantUserAs(tenant('sunrise'), $role);
    $propertyId = resv(fn (): int => Property::query()->where('code', 'CXB')->value('id'));
    DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'property_id' => $propertyId]);
    withSession([SetCurrentProperty::SESSION_KEY => $propertyId]);

    return $user;
}

function resvPlanId(): int
{
    return resv(fn (): int => RatePlan::query()->where('code', 'RO')->value('id'));
}

function lockRoom(string $number, string ...$dates): void
{
    resv(function () use ($number, $dates): void {
        $room = Room::query()->where('number', $number)->sole();
        foreach ($dates as $date) {
            InventoryLock::query()->create(['property_id' => $room->property_id, 'room_id' => $room->id, 'stay_date' => $date, 'lock_type' => LockType::Reservation]);
        }
    });
}

/**
 * @param  array<string, mixed>  $query
 * @return TestResponse<Response>
 */
function searchFor(array $query = []): TestResponse
{
    return get(tenantUrl('sunrise', '/reservation/availability?'.http_build_query([
        'check_in' => '2026-11-10', 'check_out' => '2026-11-12', 'adults' => 2, 'children' => 0, 'rate_plan' => resvPlanId(), ...$query,
    ])))->assertOk();
}

/**
 * @param  TestResponse<Response>  $page
 * @return list<string>
 */
function options(TestResponse $page, string $kind): array
{
    preg_match_all('/data-'.$kind.'-option="([^"]+)"/', (string) $page->getContent(), $matches);

    return $matches[1];
}

it('quotes the same nightly prices as the rate grid', function (): void {
    $typeId = resv(fn (): int => RoomType::query()->where('code', 'DK')->value('id'));
    $from = CarbonImmutable::parse('2026-12-10');
    $to = CarbonImmutable::parse('2027-01-03');

    $quote = resv(fn () => app(PricingService::class)->quoteRoomType(resvPlanId(), $typeId, new Occupancy(2), $from, $to));
    $grid = resv(fn (): array => app(RateLookup::class)->nightlyRates(resvPlanId(), ['room_type:'.$typeId], $from, $to->subDay())['room_type:'.$typeId]);

    expect($quote)->not->toBeNull()
        ->and(array_map(fn (PricedNight $night): string => $night->base, $quote->nights))->toBe(array_values(array_map(fn (?NightlyRate $rate) => $rate?->amount, $grid)))
        ->and(collect($quote->nights)->firstWhere('date', '2026-12-11')?->base)->toBe('7000.00')   // Friday, base weekend
        ->and(collect($quote->nights)->firstWhere('date', '2026-12-16')?->base)->toBe('9500.00')   // Peak
        ->and(collect($quote->nights)->firstWhere('date', '2026-12-31')?->base)->toBe('15000.00'); // date price
});

it('adds extra guests and the plan\'s taxes to a quote', function (): void {
    $typeId = resv(fn (): int => RoomType::query()->where('code', 'DK')->value('id'));
    $quote = resv(fn () => app(PricingService::class)->quoteRoomType(resvPlanId(), $typeId, new Occupancy(2, 1), CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-12')));

    // 2 × (6,000 + 750 child) = 13,500; SC 1,350; VAT 15% of 14,850 = 2,227.50.
    expect([$quote?->subtotal, $quote?->tax, $quote?->total])->toBe(['13500.00', '3577.50', '17077.50'])
        ->and($quote?->nights[0]->extras)->toBe('750.00');
});

it('applies the best promotion and spreads it over the nights', function (): void {
    resv(fn () => Promotion::factory()->create(['property_id' => Property::query()->where('code', 'CXB')->value('id'), 'code' => 'SAVE10', 'discount_type' => 'percent', 'discount_value' => '10']));
    actingAs(resvUser());

    $page = searchFor(['promo_code' => 'save10']);
    $typeId = resv(fn (): int => RoomType::query()->where('code', 'DK')->value('id'));
    $quote = resv(fn () => app(PricingService::class)->quoteRoomType(resvPlanId(), $typeId, new Occupancy(2), CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-12'), 'SAVE10'));

    expect([$quote?->subtotal, $quote?->discount, $quote?->net])->toBe(['12000.00', '1200.00', '10800.00'])
        ->and($quote?->promotion?->code)->toBe('SAVE10');
    $page->assertSeeHtml('data-promotion');
});

it('finds whole cottages and rooms, and hides what locks take', function (): void {
    actingAs(resvUser());

    $free = searchFor();
    expect(options($free, 'cottage'))->toBe(['C01', 'C07'])->and(options($free, 'room'))->toBe(['DK'])
        ->and(substr_count((string) $free->getContent(), 'Rooms 101, 701, 702'))->toBe(1);

    // Room 702 booked for one night of the stay: Lagoon Villa is no longer free whole.
    lockRoom('702', '2026-11-11');
    $roomBooked = searchFor();
    expect(options($roomBooked, 'cottage'))->toBe(['C01'])->and((string) $roomBooked->getContent())->toContain('Rooms 101, 701');

    // Coral booked whole (its only room locked): neither the cottage nor room 101 is offered.
    lockRoom('101', '2026-11-10', '2026-11-11');
    expect(options(searchFor(), 'cottage'))->toBe([])->and((string) searchFor()->getContent())->toContain('Rooms 701');

    // Nights outside the stay do not count.
    expect(options(searchFor(['check_in' => '2026-11-12', 'check_out' => '2026-11-14']), 'cottage'))->toBe(['C01', 'C07']);
});

it('prices a whole cottage by its cottage-type rate, falling back to its rooms', function (): void {
    actingAs(resvUser());

    // Family Villa has a cottage-type rate (15,000 + SC + VAT = 18,975 a night).
    expect((string) searchFor()->getContent())->toContain('data-total="37950.00"');

    // Honeymoon (Coral) has none: its room's rate is used, less the whole-cottage discount (0%).
    $coral = resv(fn (): int => Cottage::query()->where('code', 'C01')->value('id'));
    $quote = resv(fn () => app(PricingService::class)->quoteCottage(resvPlanId(), $coral, new Occupancy(2), CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-12')));
    expect([$quote?->subtotal, $quote?->nights[0]->source])->toBe(['12000.00', 'rooms']);
});

it('shows restrictions that stop a stay, and marks options too small for the party', function (): void {
    resv(fn () => RateRestriction::factory()->create(['property_id' => Property::query()->where('code', 'CXB')->value('id'), 'date' => '2026-12-30', 'min_stay' => 2]));
    actingAs(resvUser());

    $short = searchFor(['check_in' => '2026-12-30', 'check_out' => '2026-12-31']);
    $short->assertSeeHtml('data-violation="min_stay"')->assertSee(trans_choice('Minimum stay :count night|Minimum stay :count nights', 2));
    searchFor(['check_in' => '2026-12-30', 'check_out' => '2027-01-01'])->assertDontSeeHtml('data-violation="min_stay"');

    $big = searchFor(['adults' => 5]);
    $big->assertSee(__('Party needs more than one room'))->assertSee(__('Too small for the party'));
});

it('validates the search and needs the availability permission', function (): void {
    actingAs(resvUser());
    get(tenantUrl('sunrise', '/reservation/availability'))->assertOk()->assertDontSeeHtml('data-search-summary');
    get(tenantUrl('sunrise', '/reservation/availability?check_in=2026-11-12&check_out=2026-11-10&adults=0&rate_plan=999999'))
        ->assertSessionHasErrors(['check_out', 'adults', 'rate_plan']);
    get(tenantUrl('sunrise', '/reservation/availability?check_in=2026-11-01&check_out=2027-02-01&adults=2&rate_plan='.resvPlanId()))
        ->assertSessionHasErrors('check_out');

    actingAs(resvUser(DefaultRole::Chef));
    get(tenantUrl('sunrise', '/reservation/availability'))->assertForbidden();
});

it('refuses a second lock on the same room and night', function (): void {
    lockRoom('701', '2026-11-10');

    expect(fn () => lockRoom('701', '2026-11-10'))->toThrow(QueryException::class)
        ->and(resv(fn (): int => InventoryLock::query()->count()))->toBe(1);
});

it('keeps a room with future bookings from being deleted', function (): void {
    lockRoom('701', now()->addDays(5)->toDateString());
    resv(function (): void {
        $room = Room::query()->where('number', '702')->sole();
        InventoryLock::query()->create(['property_id' => $room->property_id, 'room_id' => $room->id, 'stay_date' => now()->addDays(5)->toDateString(), 'lock_type' => LockType::OutOfOrder]);
    });
    actingAs(resvUser(DefaultRole::GeneralManager));

    [$booked, $outOfOrder] = resv(fn (): array => [Room::query()->where('number', '701')->sole(), Room::query()->where('number', '702')->sole()]);

    delete(tenantUrl('sunrise', '/property/rooms/'.$booked->id))->assertSessionHas('error');
    delete(tenantUrl('sunrise', '/property/rooms/'.$outOfOrder->id))->assertSessionHas('success');
});

it('shows the restriction badges of the rate plan\'s restrictions through RateLookup', function (): void {
    $typeId = resv(fn (): int => RoomType::query()->where('code', 'DK')->value('id'));
    resv(fn () => RateRestriction::factory()->create(['property_id' => Property::query()->where('code', 'CXB')->value('id'), 'date' => '2026-11-11', 'min_stay' => null, 'stop_sell' => true]));

    $restrictions = resv(fn (): array => app(RateLookup::class)->restrictions(resvPlanId(), ['room_type:'.$typeId], CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-12')));
    expect($restrictions['room_type:'.$typeId]['2026-11-11'])->toEqual(new RestrictionSet(stopSell: true));

    actingAs(resvUser());
    searchFor()->assertSeeHtml('data-violation="stop_sell"');
});
