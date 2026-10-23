<?php

/*
| Reservation API (Step 8.1): availability, quotes and bookings over /api/v1/reservation with a token, using the same actions as the
| screens. Setup (booking-setup.php): Sunrise CXB, Deluxe King rooms 401/402 and 701–703 / 801–803 at 6,000 a night, villas C07 and
| C08 sold whole at 15,000, Room Only plan, SC 10% + VAT 15% compound, deposit 30%.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Guest\Models\Guest;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\BookingItemsResolver;

require_once __DIR__.'/../Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * A reservations agent of Sunrise working at CXB, and a token with the abilities (default: all they hold).
 *
 * @param  list<string>|null  $abilities
 */
function raToken(?array $abilities = null): string
{
    $agent = tenantUserAs(tenant('sunrise'), DefaultRole::ReservationAgent, ['email' => 'agent'.random_int(1, 999_999).'@sunrise.test']);
    DB::table('property_user')->insert(['tenant_id' => $agent->tenant_id, 'user_id' => $agent->id, 'property_id' => bookingIds()['property']]);

    return apiToken($agent, $abilities);
}

/**
 * A stay of two nights from 30 days ahead.
 *
 * @param  list<array<string, mixed>>  $items
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function raStay(array $items, array $extra = []): array
{
    $ids = bookingIds();

    return [
        'property_id' => $ids['property'], 'rate_plan_id' => $ids['plan'],
        'check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(32)->toDateString(),
        'items' => $items, ...$extra,
    ];
}

function raUrl(string $path): string
{
    return tenantUrl('sunrise', '/api/v1/reservation'.$path);
}

it('searches availability for one plan or every plan', function (): void {
    $ids = bookingIds();
    $token = raToken();
    $query = '?property_id='.$ids['property'].'&check_in='.now()->addDays(30)->toDateString().'&check_out='.now()->addDays(32)->toDateString().'&adults=2';

    $response = api($token)->getJson(raUrl('/availability'.$query.'&rate_plan_id='.$ids['plan']))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rate_plan.code', 'RO')
        ->assertJsonPath('data.0.room_types.0.name', 'Deluxe King')
        ->assertJsonPath('data.0.room_types.0.bookable', true)
        ->assertJsonPath('data.0.room_types.0.price.total', '15180.00')
        ->assertJsonPath('meta.adults', 2);

    expect(array_column((array) $response->json('data.0.room_types.0.free_rooms'), 'number'))->toContain('401', '701', '801')
        ->and(array_column((array) $response->json('data.0.cottages'), 'code'))->toEqualCanonicalizing(['C07', 'C08']);

    api($token)->getJson(raUrl('/availability'.$query))->assertOk()->assertJsonCount(1, 'data');
});

it('validates and authorizes the availability search', function (): void {
    $ids = bookingIds();

    api(raToken())->getJson(raUrl('/availability?property_id='.$ids['property'].'&check_in=2020-01-01&check_out=2020-01-02&adults=0'))
        ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors(['check_in', 'adults']);
    api(raToken(['reservation.booking.view']))->getJson(raUrl('/availability?property_id='.$ids['property']))->assertForbidden();
    api('')->getJson(raUrl('/availability'))->assertUnauthorized();
});

it('prices a stay, and saves it as a quote for a guest', function (): void {
    $ids = bookingIds();
    $token = raToken();

    api($token)->postJson(raUrl('/quotes'), raStay([['type' => 'room', 'room_id' => $ids['rooms']['401'], 'adults' => 2]]))->assertOk()
        ->assertJsonPath('data.total', '15180.00')
        ->assertJsonPath('data.deposit.amount', '4554.00')
        ->assertJsonPath('data.items.0.room_id', $ids['rooms']['401'])
        ->assertJsonCount(2, 'data.items.0.price.nights');

    api($token)->postJson(raUrl('/quotes'), raStay([['type' => 'room', 'room_id' => $ids['rooms']['401'], 'adults' => 2]], ['save' => true]))
        ->assertUnprocessable()->assertJsonValidationErrors('guest_id');

    $saved = api($token)->postJson(raUrl('/quotes'), raStay([['type' => 'cottage', 'cottage_id' => $ids['cottages']['C07'], 'adults' => 4]], ['save' => true, 'guest_id' => $ids['guest']]))
        ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.guest_id', $ids['guest']);

    api($token)->getJson(raUrl('/quotes/'.$saved->json('data.id')))->assertOk()->assertJsonPath('data.code', $saved->json('data.code'))->assertJsonCount(1, 'data.items');
    expect(booking(fn (): int => Quote::query()->count()))->toBe(1)
        ->and(booking(fn (): int => InventoryLock::query()->count()))->toBe(0);

    api(raToken(['reservation.quote.view']))->postJson(raUrl('/quotes'), raStay([['type' => 'room', 'room_id' => $ids['rooms']['401'], 'adults' => 2]]))->assertForbidden();
});

it('books a room by number or by type for an existing guest', function (): void {
    $ids = bookingIds();
    bookStay(['401'], now()->addDays(30)->toDateString(), now()->addDays(32)->toDateString());

    $response = api(raToken())->postJson(raUrl('/reservations'), raStay([
        ['type' => 'room', 'room_id' => $ids['rooms']['701'], 'adults' => 2],
        ['type' => 'room', 'room_type_id' => booking(fn (): int => (int) DB::table('room_types')->value('id')), 'adults' => 1],
    ], ['guest_id' => $ids['guest'], 'special_requests' => 'Late arrival']))->assertCreated()
        ->assertJsonPath('data.status', 'tentative')
        ->assertJsonPath('data.source', 'api')
        ->assertJsonPath('data.primary_guest_id', $ids['guest'])
        ->assertJsonPath('data.special_requests', 'Late arrival')
        ->assertJsonCount(2, 'data.items');

    $reservation = freshReservation((int) $response->json('data.id'));
    $rooms = $reservation->items->pluck('room_id')->all();
    expect($reservation->source)->toBe(ReservationSource::Api)
        ->and($rooms)->toContain($ids['rooms']['701'])
        ->and(in_array($ids['rooms']['401'], $rooms, true))->toBeFalse()
        ->and(booking(fn (): int => InventoryLock::query()->where('reservation_id', $reservation->id)->count()))->toBe(4);
});

it('registers a new guest with the booking when the token may create guests', function (): void {
    $ids = bookingIds();
    $stay = raStay([['type' => 'cottage', 'cottage_id' => $ids['cottages']['C08'], 'adults' => 3]], ['guest' => ['first_name' => 'Nadia', 'last_name' => 'Karim', 'email' => 'nadia@example.com', 'nationality_code' => 'bd']]);

    api(raToken(['reservation.booking.create']))->postJson(raUrl('/reservations'), $stay)
        ->assertForbidden()->assertJsonPath('code', 'forbidden');

    $response = api(raToken())->postJson(raUrl('/reservations'), $stay)->assertCreated();
    $guest = booking(fn (): Guest => Guest::query()->findOrFail($response->json('data.primary_guest_id')));
    expect($guest->full_name)->toBe('Nadia Karim')->and($guest->nationality_code)->toBe('BD');
});

it('refuses what cannot be booked', function (): void {
    $ids = bookingIds();
    $token = raToken();
    bookStay(['401'], now()->addDays(30)->toDateString(), now()->addDays(32)->toDateString());
    $blacklisted = booking(fn (): int => Guest::factory()->blacklisted()->create()->id);

    api($token)->postJson(raUrl('/reservations'), raStay([['type' => 'room', 'room_id' => $ids['rooms']['401'], 'adults' => 2]], ['guest_id' => $ids['guest']]))
        ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors('items.0');
    api($token)->postJson(raUrl('/reservations'), raStay([['type' => 'room', 'room_id' => $ids['rooms']['402'], 'adults' => 5]], ['guest_id' => $ids['guest']]))
        ->assertUnprocessable()->assertJsonValidationErrors('items.0');
    api($token)->postJson(raUrl('/reservations'), raStay([['type' => 'room', 'adults' => 2]], ['guest_id' => $ids['guest']]))
        ->assertUnprocessable()->assertJsonValidationErrors('items.0');
    api($token)->postJson(raUrl('/reservations'), raStay([['type' => 'room', 'room_id' => $ids['rooms']['402'], 'adults' => 2]], ['guest_id' => $blacklisted]))
        ->assertUnprocessable()->assertJsonPath('code', 'guest_blacklisted');
    api($token)->postJson(raUrl('/reservations'), raStay([['type' => 'room', 'room_id' => $ids['rooms']['402'], 'adults' => 2]], ['guest_id' => $ids['guest'], 'deposit_percent' => '5']))
        ->assertUnprocessable()->assertJsonPath('code', 'deposit_outside_policy');
    api($token)->postJson(raUrl('/reservations'), raStay([['type' => 'room', 'room_id' => $ids['rooms']['402'], 'adults' => 2]]))
        ->assertUnprocessable()->assertJsonValidationErrors(['guest_id', 'guest']);

    expect(booking(fn (): int => Reservation::query()->count()))->toBe(1);
});

it('answers a room taken at the last moment with a 409', function (): void {
    $ids = bookingIds();
    $checkIn = now()->addDays(30)->toDateString();
    // Another booking takes room 402 between the availability check and the save.
    app()->instance(BookingItemsResolver::class, new class(app(BookingItemsResolver::class)) extends BookingItemsResolver
    {
        public function __construct(private readonly BookingItemsResolver $inner) {}

        public function resolve(int $propertyId, int $ratePlanId, CarbonImmutable $checkIn, CarbonImmutable $checkOut, array $rows, ?string $promoCode = null): array
        {
            $items = $this->inner->resolve($propertyId, $ratePlanId, $checkIn, $checkOut, $rows, $promoCode);
            bookStay(['402'], $checkIn->toDateString(), $checkOut->toDateString());

            return $items;
        }
    });

    api(raToken())->postJson(raUrl('/reservations'), raStay([['type' => 'room', 'room_id' => $ids['rooms']['402'], 'adults' => 2]], ['guest_id' => $ids['guest']]))
        ->assertConflict()->assertJsonPath('code', 'room_no_longer_available')->assertJsonPath('taken.402.0', $checkIn);
});

it('books once per Idempotency-Key', function (): void {
    $ids = bookingIds();
    $token = raToken();
    $stay = raStay([['type' => 'room', 'room_id' => $ids['rooms']['402'], 'adults' => 2]], ['guest_id' => $ids['guest']]);

    $first = api($token)->withHeader('Idempotency-Key', 'order-77')->postJson(raUrl('/reservations'), $stay)->assertCreated();
    api($token)->withHeader('Idempotency-Key', 'order-77')->postJson(raUrl('/reservations'), $stay)
        ->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.code', $first->json('data.code'));
    api($token)->withHeader('Idempotency-Key', 'order-77')->postJson(raUrl('/reservations'), [...$stay, 'special_requests' => 'Changed'])
        ->assertConflict()->assertJsonPath('code', 'idempotency_conflict');

    expect(booking(fn (): int => Reservation::query()->count()))->toBe(1);
});

it('lists, shows and cancels bookings', function (): void {
    $ids = bookingIds();
    $token = raToken();
    $old = bookStay(['401'], now()->addDays(30)->toDateString(), now()->addDays(32)->toDateString());
    $other = bookStay(['C08'], now()->addDays(40)->toDateString(), now()->addDays(41)->toDateString());

    api($token)->getJson(raUrl('/reservations'))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
    api($token)->getJson(raUrl('/reservations?arrival_from='.now()->addDays(35)->toDateString()))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);
    api($token)->getJson(raUrl('/reservations?status=cancelled'))->assertOk()->assertJsonCount(0, 'data');
    api($token)->getJson(raUrl('/reservations?per_page=500'))->assertUnprocessable()->assertJsonValidationErrors('per_page');
    api($token)->getJson(raUrl('/reservations/'.$old->id))->assertOk()->assertJsonPath('data.code', $old->code)->assertJsonPath('data.guest_ids', [$ids['guest']]);
    api($token)->getJson(raUrl('/reservations/999999'))->assertNotFound()->assertJsonPath('code', 'not_found');

    api($token)->postJson(raUrl('/reservations/'.$old->id.'/cancel'), [])->assertUnprocessable()->assertJsonValidationErrors('reason');
    api($token)->postJson(raUrl('/reservations/'.$old->id.'/cancel'), ['reason' => 'Plans changed'])->assertOk()
        ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.cancellation.reason', 'Plans changed');
    api($token)->postJson(raUrl('/reservations/'.$old->id.'/cancel'), ['reason' => 'Again'])->assertConflict()->assertJsonPath('code', 'reservation_not_changeable');

    expect(freshReservation($old->id)->status)->toBe(ReservationStatus::Cancelled);
    api(raToken(['reservation.booking.view']))->postJson(raUrl('/reservations/'.$other->id.'/cancel'), ['reason' => 'X'])->assertForbidden();
});

it('never shows another company\'s bookings', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'moonlight'])));
    $theirs = bookStay(['401'], now()->addDays(30)->toDateString(), now()->addDays(32)->toDateString(), slug: 'moonlight');
    $token = raToken();

    api($token)->getJson(raUrl('/reservations'))->assertOk()->assertJsonCount(0, 'data');
    api($token)->getJson(raUrl('/reservations/'.$theirs->id))->assertNotFound();
    api($token)->postJson(raUrl('/reservations/'.$theirs->id.'/cancel'), ['reason' => 'X'])->assertNotFound();
    expect(freshReservation($theirs->id, 'moonlight')->status)->not->toBe(ReservationStatus::Cancelled);
});
