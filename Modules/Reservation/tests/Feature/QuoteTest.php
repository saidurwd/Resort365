<?php

/*
| Quotes (Step 1.8). "Done when": a quote converts into a booking at the quoted prices.
| Room 401: 6,000 a night for two → 7,590.00 with SC 10% + VAT 15%; 3 nights = 22,770.00.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Modules\Rates\Models\Rate;
use Modules\Reservation\Actions\ConvertQuote;
use Modules\Reservation\Actions\SaveQuote;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\QuoteStatus;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\QuoteNotOpen;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Notifications\GuestMessage;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

require_once __DIR__.'/../Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

/**
 * @param  list<string>  $units  room numbers or cottage codes
 */
function quoteStay(array $units = ['401'], string $checkIn = '2026-11-10', string $checkOut = '2026-11-13'): Quote
{
    $ids = bookingIds();
    $items = array_map(fn (string $unit): BookingItem => isset($ids['cottages'][$unit])
        ? new BookingItem(ItemType::Cottage, $ids['cottages'][$unit], $ids['plan'], 2)
        : new BookingItem(ItemType::Room, $ids['rooms'][$unit], $ids['plan'], 2), $units);

    return booking(fn (): Quote => SaveQuote::make()->handle(new NewReservation($ids['property'], CarbonImmutable::parse($checkIn),
        CarbonImmutable::parse($checkOut), $items, $ids['guest'], specialRequests: 'Sea view if possible')));
}

it('saves a priced proposal night by night without holding rooms', function (): void {
    $quote = quoteStay(['401', 'C07']);

    expect($quote->code)->toStartWith('QUO-')
        ->and($quote->status)->toBe(QuoteStatus::Draft)
        ->and([$quote->grand_total, $quote->deposit_percent])->toBe(['79695.00', '30.00'])
        ->and($quote->valid_until->toDateString())->toBe(now()->addDays(7)->toDateString())
        ->and($quote->items)->toHaveCount(2)
        ->and(booking(fn (): int => $quote->items()->withCount('nights')->get()->sum('nights_count')))->toBe(6)
        ->and(booking(fn (): int => InventoryLock::query()->count()))->toBe(0);
});

it('books a quote at the quoted prices even after the rates changed', function (): void {
    $quote = quoteStay(['401']);
    booking(fn () => Rate::query()->update(['amount' => '9000.00']));

    $reservation = booking(fn (): Reservation => ConvertQuote::make()->handle($quote));
    $booked = freshReservation($reservation->id);
    $converted = booking(fn (): Quote => Quote::query()->findOrFail($quote->id));

    expect([$booked->grand_total, $booked->deposit_required, $booked->status])->toBe(['22770.00', '6831.00', ReservationStatus::Tentative])
        ->and($booked->special_requests)->toBe('Sea view if possible')
        ->and($booked->internal_notes)->toContain($quote->code)
        ->and(booking(fn (): array => $booked->items->first()?->nights()->pluck('total_amount')->all() ?? []))->toBe(['7590.00', '7590.00', '7590.00'])
        ->and($booked->deposit_due_at?->isFuture())->toBeTrue()
        ->and([$converted->status, $converted->reservation_id])->toBe([QuoteStatus::Accepted, $reservation->id])
        ->and(booking(fn (): int => InventoryLock::query()->where('reservation_id', $reservation->id)->count()))->toBe(3);

    // A fresh booking of the same stay now costs the new rate.
    expect(bookStay(['402'])->grand_total)->not->toBe('22770.00');
});

it('refuses to book a quote twice, an expired quote, or rooms taken since', function (): void {
    $quote = quoteStay(['401']);
    booking(fn () => ConvertQuote::make()->handle($quote));
    expect(fn () => booking(fn () => ConvertQuote::make()->handle($quote)))->toThrow(QuoteNotOpen::class);

    $expired = quoteStay(['402']);
    booking(fn () => $expired->forceFill(['valid_until' => now()->subDay()->toDateString()])->save());
    expect(fn () => booking(fn () => ConvertQuote::make()->handle($expired)))->toThrow(QuoteNotOpen::class);

    $taken = quoteStay(['C08']);
    bookStay(['801']);
    expect(fn () => booking(fn () => ConvertQuote::make()->handle($taken)))->toThrow(RoomNoLongerAvailable::class)
        ->and(booking(fn (): QuoteStatus => Quote::query()->findOrFail($taken->id)->status))->toBe(QuoteStatus::Draft);
});

describe('screens', function (): void {
    it('saves a quote from the booking wizard', function (): void {
        staffUser();
        $ids = bookingIds();
        post(tenantUrl('sunrise', '/reservation/bookings/new'), ['check_in' => '2026-11-10', 'check_out' => '2026-11-13', 'adults' => 2, 'children' => 0, 'rate_plan' => $ids['plan']]);
        post(tenantUrl('sunrise', '/reservation/bookings/new/choose'), ['rooms' => [$ids['rooms']['401']]]);
        post(tenantUrl('sunrise', '/reservation/bookings/new/guest'), ['guest_mode' => 'existing', 'guest_id' => $ids['guest'], 'source' => 'email']);
        post(tenantUrl('sunrise', '/reservation/bookings/new/pricing'), ['action' => 'continue', 'deposit_percent' => '30']);

        get(tenantUrl('sunrise', '/reservation/bookings/new/confirm'))->assertOk()->assertSeeHtml('data-wizard-quote');
        $response = post(tenantUrl('sunrise', '/reservation/bookings/new/quote'))->assertSessionHas('success');

        $quote = booking(fn (): Quote => Quote::query()->sole());
        $response->assertRedirect(tenantUrl('sunrise', '/reservation/quotes/'.$quote->id));
        expect($quote->grand_total)->toBe('22770.00')
            ->and(booking(fn (): int => Reservation::query()->count()))->toBe(0);
    });

    it('lists, shows, emails, declines and books quotes', function (): void {
        staffUser();
        $quote = quoteStay(['401']);
        $declined = quoteStay(['402']);

        get(tenantUrl('sunrise', '/reservation/quotes'))->assertOk()->assertSeeHtml('id="quotes-table"');
        expect(getJson(tenantUrl('sunrise', '/reservation/quotes/data?draw=1&start=0&length=25'))->json('recordsFiltered'))->toBe(2);

        get(tenantUrl('sunrise', "/reservation/quotes/{$quote->id}"))->assertOk()->assertSee($quote->code)->assertSee('22,770.00')->assertSeeHtml('data-quote-convert');
        $pdf = get(tenantUrl('sunrise', "/reservation/quotes/{$quote->id}/pdf"))->assertOk()->assertHeader('content-type', 'application/pdf');
        expect((string) $pdf->getContent())->toStartWith('%PDF');

        post(tenantUrl('sunrise', "/reservation/quotes/{$quote->id}/send"))->assertSessionHas('success');
        Notification::assertSentOnDemand(GuestMessage::class, fn (GuestMessage $message, array $channels, AnonymousNotifiable $notifiable): bool => $message->subject === "Your quotation {$quote->code} from Sunrise Cox's Bazar"
            && array_keys($message->attachments) === [$quote->code.'.pdf'] && str_contains($message->body, 'BDT 22,770.00') && isset($notifiable->routes['mail']['rahim@example.com']));
        expect(booking(fn (): QuoteStatus => Quote::query()->findOrFail($quote->id)->status))->toBe(QuoteStatus::Sent);

        post(tenantUrl('sunrise', "/reservation/quotes/{$declined->id}/decline"))->assertSessionHas('success');
        post(tenantUrl('sunrise', "/reservation/quotes/{$declined->id}/convert"))->assertSessionHas('error');

        $response = post(tenantUrl('sunrise', "/reservation/quotes/{$quote->id}/convert"))->assertSessionHas('success');
        $reservation = booking(fn (): Reservation => Reservation::query()->sole());
        $response->assertRedirect(tenantUrl('sunrise', '/reservation/bookings/'.$reservation->id.'#payments'));
    });

    it('is refused to staff without the quote permissions', function (): void {
        staffUser(DefaultRole::HousekeepingSupervisor);
        $quote = quoteStay(['401']);

        get(tenantUrl('sunrise', '/reservation/quotes'))->assertForbidden();
        get(tenantUrl('sunrise', "/reservation/quotes/{$quote->id}"))->assertForbidden();
        post(tenantUrl('sunrise', "/reservation/quotes/{$quote->id}/convert"))->assertForbidden();
    });

    it('does not show another tenant\'s quote', function (): void {
        bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
        $ids = bookingIds('greenvalley');
        $theirs = booking(fn (): Quote => SaveQuote::make()->handle(new NewReservation($ids['property'], CarbonImmutable::parse('2026-11-10'),
            CarbonImmutable::parse('2026-11-12'), [new BookingItem(ItemType::Room, $ids['rooms']['401'], $ids['plan'], 2)], $ids['guest'])), 'greenvalley');
        staffUser();

        get(tenantUrl('sunrise', "/reservation/quotes/{$theirs->id}"))->assertNotFound();
    });
});
