<?php

/*
| Booking on the public website (Step 8.2b): search per rate plan sold online, a cart of rooms and whole cottages, checkout with
| deposit or full payment, the guest's signed booking page with cancelling, the email link, the abuse limits and the embeddable
| widget. Setup (booking-setup.php): Sunrise CXB, Deluxe King rooms (401, 402, 701–703, 801–803) at 6,000 a night, villas C07 and
| C08 sold whole at 15,000 (C04 by the room only), Room Only plan (front desk and online), SC 10% + VAT 15%, deposit 30%.
*/

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Models\OnlinePayment;
use Modules\Billing\Models\PaymentGateway;
use Modules\Billing\Services\Gateways\TestGateway;
use Modules\Guest\Models\Guest;
use Modules\Property\Models\RoomType;
use Modules\Rates\Models\RatePlan;
use Modules\Reservation\Contracts\GuestEmailLinks;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Website\Enums\PayChoice;
use Modules\Website\Models\WebsiteBooking;
use Modules\Website\Models\WebsiteSite;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => WebsiteSite::factory()->create(['property_id' => bookingIds()['property'], 'slug' => 'sunrise', 'name' => 'Sunrise Beach Resort', 'embed_origins' => "https://www.sunrise.example\nhttps://partner.example:8443"]));
});

function bfUrl(string $path = ''): string
{
    return tenantUrl('sunrise', '/resort/sunrise'.$path);
}

/**
 * Two nights from 30 days ahead for two adults.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function bfStay(array $extra = []): array
{
    return ['check_in' => now()->addDays(30)->toDateString(), 'check_out' => now()->addDays(32)->toDateString(), 'adults' => 2, 'children' => 0, ...$extra];
}

/** @param  array<string, mixed>  $extra */
function bfAdd(string $type, int $id, array $extra = []): void
{
    post(bfUrl('/cart'), [...bfStay(), 'rate_plan_id' => bookingIds()['plan'], 'type' => $type, 'id' => $id, ...$extra]);
}

function bfRoomType(): int
{
    return booking(fn (): int => (int) RoomType::query()->value('id'));
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function bfGuest(array $extra = []): array
{
    return ['first_name' => 'Nadia', 'last_name' => 'Karim', 'email' => 'nadia@example.com', 'phone' => '01911-000003', 'pay_choice' => 'deposit', 'accept_policies' => '1', ...$extra];
}

it('searches the plans sold online for the dates and party', function (): void {
    booking(fn () => RatePlan::factory()->create(['property_id' => bookingIds()['property'], 'code' => 'CORP', 'name' => 'Corporate desk rate', 'channels' => ['front_desk']]));

    get(bfUrl('/book'))->assertOk()->assertSee(__('Choose your dates and the number of guests to see what is free.'))->assertSeeHtml('data-cart-empty');
    get(bfUrl('/book?'.http_build_query(bfStay())))->assertOk()
        ->assertSeeHtml('data-plan="RO"')->assertDontSeeHtml('data-plan="CORP"')
        ->assertSeeHtml('data-add="room:'.bfRoomType().'"')
        ->assertSeeHtml('data-add="cottage:'.bookingIds()['cottages']['C07'].'"')
        ->assertDontSeeHtml('data-add="cottage:'.bookingIds()['cottages']['C04'].'"')
        ->assertSee('BDT 15,180');

    get(bfUrl('/book?'.http_build_query(bfStay(['adults' => 12]))))->assertOk()->assertSee(__('Too many guests for this one.'));
    get(bfUrl('/book?'.http_build_query(bfStay(['check_out' => now()->addDays(29)->toDateString()]))))->assertRedirect()->assertSessionHasErrors('check_out');
});

it('fills a cart with rooms and whole cottages and prices it', function (): void {
    bfAdd('cottage', bookingIds()['cottages']['C07']);
    bfAdd('room', bfRoomType());

    $page = get(bfUrl('/book'))->assertOk()->assertSeeHtml('data-cart-item="1"')->assertSeeHtml('data-checkout');
    // 15,000 + 6,000 a night for two nights, with SC 10% and VAT 15%: 53,130.
    $page->assertSeeHtml('data-cart-total="53130.00"')->assertSee('Lagoon Villa');
    get(bfUrl())->assertOk()->assertSeeHtml('data-cart-count');

    bfAdd('cottage', bookingIds()['cottages']['C07']);
    expect(session('error'))->toBe(__('This cottage is already in your cart.'));

    $other = booking(fn (): int => RatePlan::factory()->create(['property_id' => bookingIds()['property'], 'code' => 'BB'])->id);
    bfAdd('room', bfRoomType(), ['rate_plan_id' => $other]);
    expect(session('error'))->toContain('another rate plan');

    delete(bfUrl('/cart/0'));
    get(bfUrl('/book'))->assertOk()->assertDontSeeHtml('data-cart-item="1"')->assertSeeHtml('data-cart-total="15180.00"');
});

it('books the cart as a tentative website booking holding the rooms', function (): void {
    bfAdd('cottage', bookingIds()['cottages']['C07']);
    bfAdd('room', bfRoomType());

    get(bfUrl('/checkout'))->assertOk()->assertSee('BDT 53,130')->assertSee('A deposit of 30% of the stay secures the booking.')->assertSeeHtml('name="website_url"');
    $response = post(bfUrl('/checkout'), bfGuest(['special_requests' => 'Late arrival']))->assertRedirect();

    $reservation = booking(fn (): Reservation => Reservation::query()->with('items')->sole());
    $booking = booking(fn (): WebsiteBooking => WebsiteBooking::query()->sole());
    expect($reservation->source)->toBe(ReservationSource::Website)
        ->and($reservation->status)->toBe(ReservationStatus::Tentative)
        ->and($reservation->special_requests)->toBe('Late arrival')
        ->and($reservation->items)->toHaveCount(2)
        // The room of the type is not one of C07's rooms, which the villa holds.
        ->and($reservation->items->pluck('room_id')->filter()->intersect([bookingIds()['rooms']['701'], bookingIds()['rooms']['702'], bookingIds()['rooms']['703']]))->toBeEmpty()
        ->and(booking(fn (): int => InventoryLock::query()->where('reservation_id', $reservation->id)->count()))->toBe(8)
        ->and([$booking->pay_choice, $booking->amount_due_now, $booking->email])->toBe([PayChoice::Deposit, $reservation->deposit_required, 'nadia@example.com'])
        ->and(booking(fn (): string => Guest::query()->findOrFail($reservation->primary_guest_id)->full_name))->toBe('Nadia Karim');

    expect($response->headers->get('Location'))->toContain('/resort/sunrise/booking/'.$reservation->code.'?signature=');
    get((string) $response->headers->get('Location'))->assertOk()->assertSeeHtml('data-booking-status="tentative"')->assertSeeHtml('data-pay-now')->assertSeeHtml('data-cancel');
    get(bfUrl('/book'))->assertSeeHtml('data-cart-empty');
});

it('asks the full amount when the guest chooses it, and reuses a matching guest', function (): void {
    $existing = booking(fn (): Guest => Guest::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Karim', 'email' => 'nadia@example.com', 'phone' => '+8801911000003']));
    bfAdd('room', bfRoomType());

    post(bfUrl('/checkout'), bfGuest(['pay_choice' => 'full']))->assertRedirect();

    $reservation = booking(fn (): Reservation => Reservation::query()->sole());
    expect($reservation->primary_guest_id)->toBe($existing->id)
        ->and(booking(fn (): string => WebsiteBooking::query()->sole()->amount_due_now))->toBe($reservation->grand_total);
});

it('validates the checkout, refuses robots and blacklisted guests', function (): void {
    booking(fn () => Guest::factory()->blacklisted()->create(['email' => 'banned@example.com', 'phone' => '+8801711999999']));
    get(bfUrl('/checkout'))->assertRedirect(bfUrl('/book'));
    bfAdd('room', bfRoomType());

    post(bfUrl('/checkout'), bfGuest(['email' => 'not-an-email', 'accept_policies' => null, 'pay_choice' => 'later']))->assertSessionHasErrors(['email', 'accept_policies', 'pay_choice']);
    post(bfUrl('/checkout'), bfGuest(['website_url' => 'http://spam.example']))->assertSessionHasErrors('website_url');
    post(bfUrl('/checkout'), bfGuest(['email' => 'banned@example.com', 'phone' => '01711-999999']))->assertRedirect()->assertSessionHas('error');

    expect(booking(fn (): int => Reservation::query()->count()))->toBe(0);
});

it('tells the guest when a room was taken meanwhile and keeps the cart', function (): void {
    bfAdd('cottage', bookingIds()['cottages']['C08']);
    bookStay(['C08'], now()->addDays(30)->toDateString(), now()->addDays(32)->toDateString());

    post(bfUrl('/checkout'), bfGuest())->assertRedirect(bfUrl('/book'))->assertSessionHas('error');
    get(bfUrl('/book'))->assertOk()->assertSeeHtml('data-cart-problem')->assertDontSeeHtml('data-checkout');
    expect(booking(fn (): int => Reservation::query()->count()))->toBe(1);
});

it('takes at most three open website bookings a day from one email', function (): void {
    foreach (['401', '402', '701'] as $room) {
        $reservation = bookStay([$room], now()->addDays(30)->toDateString(), now()->addDays(32)->toDateString());
        booking(fn () => WebsiteBooking::factory()->create(['website_site_id' => WebsiteSite::query()->sole()->id, 'reservation_id' => $reservation->id, 'reservation_code' => $reservation->code, 'email' => 'nadia@example.com']));
    }

    bfAdd('room', bfRoomType());
    post(bfUrl('/checkout'), bfGuest())->assertSessionHas('error', __('We could not take this booking online. Please call or email us and we will gladly help.'));
});

it('limits checkout attempts per address', function (): void {
    foreach (range(1, 5) as $ignored) {
        post(bfUrl('/checkout'), [])->assertSessionHasErrors();
    }

    post(bfUrl('/checkout'), [])->assertTooManyRequests();
});

it('shows and cancels a booking through its signed link only', function (): void {
    bfAdd('room', bfRoomType());
    $link = (string) post(bfUrl('/checkout'), bfGuest())->headers->get('Location');
    $code = booking(fn (): string => Reservation::query()->sole()->code);

    get(bfUrl('/booking/'.$code))->assertForbidden();
    get($link.'&lang=bn')->assertOk()->assertSee('বুকিং');

    $cancel = booking(fn (): string => URL::signedRoute('website.booking.cancel', ['site' => 'sunrise', 'code' => $code]));
    post(bfUrl('/booking/'.$code.'/cancel'), ['confirm' => '1'])->assertForbidden();
    post($cancel, [])->assertSessionHasErrors('confirm');
    post($cancel, ['confirm' => '1'])->assertRedirect($link)->assertSessionHas('success');

    expect(booking(fn (): ReservationStatus => Reservation::query()->sole()->status))->toBe(ReservationStatus::Cancelled);
    get($link)->assertOk()->assertSeeHtml('data-booking-status="cancelled"')->assertDontSeeHtml('data-cancel-button');
});

it('puts the booking page link in the booking emails', function (): void {
    bfAdd('room', bfRoomType());
    post(bfUrl('/checkout'), bfGuest());
    $reservation = booking(fn (): Reservation => Reservation::query()->sole());

    $links = booking(fn (): array => app(GuestEmailLinks::class)->for($reservation->id));
    expect($links)->toHaveCount(1)->and($links[0]['label'])->toBe('Manage your booking')->and($links[0]['url'])->toContain('/resort/sunrise/booking/'.$reservation->code.'?signature=')
        ->and(booking(fn (): array => app(GuestEmailLinks::class)->for(bookStay(['402'])->id)))->toBe([]);
});

it('lets only the listed websites embed the search box', function (): void {
    $widget = get(bfUrl('/widget'))->assertOk()->assertSeeHtml('target="_top"')->assertHeaderMissing('X-Frame-Options');
    expect((string) $widget->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self' https://www.sunrise.example https://partner.example:8443;");

    $page = get(bfUrl())->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    expect((string) $page->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self';");
});

it('pays online after booking when the property has a gateway, within the payment window', function (): void {
    booking(fn () => PaymentGateway::factory()->provider(GatewayProvider::Test)->create(['property_id' => bookingIds()['property']]));
    bfAdd('room', bfRoomType());

    $link = (string) post(bfUrl('/checkout'), bfGuest())->headers->get('Location');
    $reservation = booking(fn (): Reservation => Reservation::query()->sole());
    expect($reservation->deposit_due_at?->between(now()->addMinutes(14), now()->addMinutes(16)))->toBeTrue()->and($reservation->auto_cancel_unpaid)->toBeTrue();

    get($link)->assertOk()->assertSeeHtml('data-pay-with="test"')->assertSee('BDT 4,554');
    $pay = booking(fn (): string => URL::signedRoute('website.booking.pay', ['site' => 'sunrise', 'code' => $reservation->code]));
    $gatewayPage = (string) post($pay, ['provider' => 'test'])->assertRedirect()->headers->get('Location');
    expect($gatewayPage)->toContain('/pay/test/');

    $attempt = booking(fn (): OnlinePayment => OnlinePayment::query()->sole());
    $txn = 'TEST-TXN-9';
    get(tenantUrl('sunrise', '/pay/return/'.$attempt->uuid.'?'.http_build_query(['outcome' => 'paid', 'amount' => $attempt->amount, 'currency' => 'BDT', 'txn' => $txn,
        'sig' => TestGateway::sign($attempt->uuid, 'paid', $attempt->amount, $txn)])))->assertRedirect($link.'&payment=paid');

    get($link.'&payment=paid')->assertOk()->assertSeeHtml('data-payment-outcome="paid"')->assertSeeHtml('data-booking-status="confirmed"')->assertDontSeeHtml('data-pay-with');
});
