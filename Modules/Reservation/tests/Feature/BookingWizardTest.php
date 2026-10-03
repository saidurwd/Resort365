<?php

/*
| Step 1.6 "Done when": a booking for 2 whole cottages + 1 room for 3 nights is created through
| the wizard; the deposit shows correctly.
*/

use App\Http\Middleware\SetCurrentProperty;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Guest\Models\Guest;
use Modules\IAM\Models\User;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

require_once __DIR__.'/../Support/booking-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

function wizardUser(DefaultRole $role = DefaultRole::FrontDeskAgent): User
{
    $user = tenantUserAs(tenant('sunrise'), $role);
    $propertyId = bookingIds()['property'];
    DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'property_id' => $propertyId]);
    withSession([SetCurrentProperty::SESSION_KEY => $propertyId]);
    actingAs($user);

    return $user;
}

function wizardDates(string $checkIn = '2026-11-10', string $checkOut = '2026-11-13', int $adults = 13): void
{
    post(tenantUrl('sunrise', '/reservation/bookings/new'), ['check_in' => $checkIn, 'check_out' => $checkOut, 'adults' => $adults, 'children' => 0, 'rate_plan' => bookingIds()['plan']])
        ->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new/choose'));
}

it('books 2 whole cottages and a room for 3 nights through the wizard, with the deposit shown', function (): void {
    wizardUser();
    $ids = bookingIds();

    get(tenantUrl('sunrise', '/reservation/bookings/new'))->assertOk()->assertSee('Room Only');
    wizardDates();

    get(tenantUrl('sunrise', '/reservation/bookings/new/choose'))->assertOk()->assertSeeHtml('data-choose-cottage="C07"')->assertSeeHtml('data-choose-cottage="C08"')->assertDontSeeHtml('data-choose-cottage="C04"'); // rooms only
    post(tenantUrl('sunrise', '/reservation/bookings/new/choose'), ['cottages' => [$ids['cottages']['C07'], $ids['cottages']['C08']], 'rooms' => [$ids['rooms']['401']]])
        ->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new/guest'));

    get(tenantUrl('sunrise', '/reservation/bookings/new/guest'))->assertOk();
    post(tenantUrl('sunrise', '/reservation/bookings/new/guest'), ['guest_mode' => 'existing', 'guest_id' => $ids['guest'], 'source' => 'phone'])
        ->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new/pricing'));

    // The party of 13 is spread over the items (an adult each, then filling up); villas cost the same at any occupancy.
    get(tenantUrl('sunrise', '/reservation/bookings/new/pricing'))->assertOk()
        ->assertSeeInOrder([__('Grand total'), '136,620.00', __('Deposit (:percent%)', ['percent' => '30']), '40,986.00', __('Balance'), '95,634.00']);
    post(tenantUrl('sunrise', '/reservation/bookings/new/pricing'), ['action' => 'continue', 'deposit_percent' => '30', 'special_requests' => 'Late arrival'])
        ->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new/confirm'));

    get(tenantUrl('sunrise', '/reservation/bookings/new/confirm'))->assertOk()
        ->assertSee('Rahim Uddin')->assertSee('Lagoon Villa (whole cottage)')->assertSee('Room 401')->assertSee('40,986.00')->assertSeeHtml('data-deposit-due');
    $response = post(tenantUrl('sunrise', '/reservation/bookings/new/confirm'))->assertSessionHas('success');

    $reservation = booking(fn (): Reservation => Reservation::query()->with('items')->sole());
    $response->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/'.$reservation->id));

    expect($reservation->status)->toBe(ReservationStatus::Tentative)
        ->and($reservation->source)->toBe(ReservationSource::Phone)
        ->and($reservation->nights())->toBe(3)
        ->and($reservation->items)->toHaveCount(3)
        ->and([$reservation->grand_total, $reservation->deposit_required])->toBe(['136620.00', '40986.00'])
        ->and($reservation->special_requests)->toBe('Late arrival')
        ->and(booking(fn (): int => InventoryLock::query()->where('lock_type', LockType::Reservation->value)->count()))->toBe(21);

    get(tenantUrl('sunrise', '/reservation/bookings/'.$reservation->id))->assertOk()
        ->assertSee($reservation->code)->assertSee('40,986.00')->assertSee(__('Tentative'))->assertSeeHtml('data-deposit-due');

    // The wizard starts afresh, and the booked units are gone from availability.
    get(tenantUrl('sunrise', '/reservation/bookings/new/choose'))->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new'));
    wizardDates('2026-11-11', '2026-11-12', 2);
    get(tenantUrl('sunrise', '/reservation/bookings/new/choose'))->assertOk()->assertDontSeeHtml('data-choose-cottage="C07"')->assertDontSee('Room 401');
});

it('registers a new guest, warning about one with the same phone', function (): void {
    wizardUser();
    $ids = bookingIds();
    wizardDates(adults: 2);
    post(tenantUrl('sunrise', '/reservation/bookings/new/choose'), ['rooms' => [$ids['rooms']['402']]])->assertSessionHasNoErrors();

    $new = ['guest_mode' => 'new', 'first_name' => 'Karim', 'last_name' => 'Ahmed', 'phone' => '01711-000001', 'source' => 'walk_in'];
    post(tenantUrl('sunrise', '/reservation/bookings/new/guest'), $new)->assertRedirect()->assertSessionHas('guest_duplicates');
    get(tenantUrl('sunrise', '/reservation/bookings/new/guest'))->assertOk()->assertSee(__('This guest may already exist'))->assertSee('Rahim Uddin');

    post(tenantUrl('sunrise', '/reservation/bookings/new/guest'), [...$new, 'confirm_new' => '1'])->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new/pricing'));
    post(tenantUrl('sunrise', '/reservation/bookings/new/pricing'), ['action' => 'continue'])->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new/confirm'));
    get(tenantUrl('sunrise', '/reservation/bookings/new/confirm'))->assertOk()->assertSee('Karim Ahmed')->assertSee(__('new guest'));
    post(tenantUrl('sunrise', '/reservation/bookings/new/confirm'))->assertSessionHas('success');

    $reservation = booking(fn (): Reservation => Reservation::query()->sole());
    expect(booking(fn (): string => Guest::query()->findOrFail($reservation->primary_guest_id)->first_name))->toBe('Karim')
        ->and(booking(fn (): int => Guest::query()->count()))->toBe(2);
});

it('validates each step and keeps the steps in order', function (): void {
    wizardUser();

    get(tenantUrl('sunrise', '/reservation/bookings/new/pricing'))->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new/guest'));
    post(tenantUrl('sunrise', '/reservation/bookings/new'), ['check_in' => '2026-11-13', 'check_out' => '2026-11-10', 'adults' => 0, 'rate_plan' => 999999])
        ->assertSessionHasErrors(['check_out', 'adults', 'rate_plan']);

    wizardDates(adults: 2);
    post(tenantUrl('sunrise', '/reservation/bookings/new/choose'), [])->assertSessionHasErrors('cottages');
    post(tenantUrl('sunrise', '/reservation/bookings/new/choose'), ['cottages' => [bookingIds()['cottages']['C04']]])->assertSessionHasErrors('cottages'); // rooms-only cottage

    post(tenantUrl('sunrise', '/reservation/bookings/new/choose'), ['rooms' => [bookingIds()['rooms']['401']]])->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/reservation/bookings/new/guest'), ['guest_mode' => 'new', 'first_name' => '', 'source' => 'fax'])->assertSessionHasErrors(['first_name', 'source']);
    post(tenantUrl('sunrise', '/reservation/bookings/new/guest'), ['guest_mode' => 'existing', 'guest_id' => 999999, 'source' => 'phone'])->assertSessionHasErrors('guest_id');
});

it('needs a manager to allow a deposit below the policy minimum', function (): void {
    wizardUser();
    $ids = bookingIds();
    wizardDates(adults: 2);
    post(tenantUrl('sunrise', '/reservation/bookings/new/choose'), ['rooms' => [$ids['rooms']['401']]]);
    post(tenantUrl('sunrise', '/reservation/bookings/new/guest'), ['guest_mode' => 'existing', 'guest_id' => $ids['guest'], 'source' => 'phone']);

    post(tenantUrl('sunrise', '/reservation/bookings/new/pricing'), ['action' => 'continue', 'deposit_percent' => '10'])
        ->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new/pricing'))->assertSessionHasErrors('deposit_percent');

    wizardUser(DefaultRole::FrontOfficeManager);
    wizardDates(adults: 2);
    post(tenantUrl('sunrise', '/reservation/bookings/new/choose'), ['rooms' => [$ids['rooms']['401']]]);
    post(tenantUrl('sunrise', '/reservation/bookings/new/guest'), ['guest_mode' => 'existing', 'guest_id' => $ids['guest'], 'source' => 'phone']);
    post(tenantUrl('sunrise', '/reservation/bookings/new/pricing'), ['action' => 'continue', 'deposit_percent' => '10'])
        ->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new/confirm'));
    post(tenantUrl('sunrise', '/reservation/bookings/new/confirm'))->assertSessionHas('success');

    expect(booking(fn (): Reservation => Reservation::query()->sole())->deposit_override_by)->not->toBeNull();
});

it('sends the user back to choose again when a room was taken meanwhile', function (): void {
    wizardUser();
    $ids = bookingIds();
    wizardDates(adults: 2);
    post(tenantUrl('sunrise', '/reservation/bookings/new/choose'), ['rooms' => [$ids['rooms']['401']]]);
    post(tenantUrl('sunrise', '/reservation/bookings/new/guest'), ['guest_mode' => 'existing', 'guest_id' => $ids['guest'], 'source' => 'phone']);
    post(tenantUrl('sunrise', '/reservation/bookings/new/pricing'), ['action' => 'continue']);

    // Someone else takes room 401 for one of the nights.
    booking(fn () => InventoryLock::query()->create(['property_id' => $ids['property'], 'room_id' => $ids['rooms']['401'], 'stay_date' => '2026-11-11', 'lock_type' => LockType::Hold]));

    post(tenantUrl('sunrise', '/reservation/bookings/new/confirm'))->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/new/choose'))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, '401') && str_contains($message, '2026-11-11'));

    expect(booking(fn (): int => Reservation::query()->count()))->toBe(0);
});

it('keeps booking to staff with the booking permission', function (): void {
    $reservation = booking(fn (): Reservation => Reservation::factory()->create(['property_id' => bookingIds()['property']]));

    wizardUser(DefaultRole::Chef);
    get(tenantUrl('sunrise', '/reservation/bookings/new'))->assertForbidden();
    post(tenantUrl('sunrise', '/reservation/bookings/new/confirm'))->assertForbidden();
    get(tenantUrl('sunrise', '/reservation/bookings/'.$reservation->id))->assertForbidden();

    wizardUser(DefaultRole::Auditor);
    get(tenantUrl('sunrise', '/reservation/bookings/'.$reservation->id))->assertOk();
    get(tenantUrl('sunrise', '/reservation/bookings/new'))->assertForbidden();
});
