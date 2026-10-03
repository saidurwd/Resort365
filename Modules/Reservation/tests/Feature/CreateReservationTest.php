<?php

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\Promotion;
use Modules\Reservation\Actions\CreateReservation;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationCreated;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItemNight;

require_once __DIR__.'/../Support/booking-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * @param  list<BookingItem>  $items
 * @param  array<string, mixed>  $overrides
 */
function newBooking(array $items, array $overrides = []): NewReservation
{
    $ids = bookingIds();

    return NewReservation::from([
        'propertyId' => $ids['property'], 'checkIn' => CarbonImmutable::parse('2026-11-10'), 'checkOut' => CarbonImmutable::parse('2026-11-13'),
        'items' => $items, 'primaryGuestId' => $ids['guest'], ...$overrides,
    ]);
}

function cottageItem(string $code, int $adults = 2): BookingItem
{
    return new BookingItem(ItemType::Cottage, bookingIds()['cottages'][$code], bookingIds()['plan'], $adults);
}

function roomItem(string $number, int $adults = 2, int $children = 0): BookingItem
{
    return new BookingItem(ItemType::Room, bookingIds()['rooms'][$number], bookingIds()['plan'], $adults, $children);
}

it('creates a tentative reservation with items, nightly snapshots, guest and locks', function (): void {
    Event::fake([ReservationCreated::class]);
    $reservation = booking(fn (): Reservation => CreateReservation::make()->handle(newBooking([cottageItem('C07', 6), cottageItem('C08', 5), roomItem('401')])));

    // (2 × 15,000 + 6,000) × 3 = 108,000; SC 10,800; VAT 15% of 118,800 = 17,820.
    expect($reservation->code)->toStartWith('RSV-')
        ->and($reservation->status)->toBe(ReservationStatus::Tentative)
        ->and([$reservation->subtotal, $reservation->tax_total, $reservation->grand_total])->toBe(['108000.00', '28620.00', '136620.00'])
        ->and([$reservation->deposit_percent, $reservation->deposit_required, $reservation->balance_due])->toBe(['30.00', '40986.00', '136620.00'])
        ->and($reservation->adults)->toBe(13)
        ->and($reservation->auto_cancel_unpaid)->toBeTrue()
        ->and($reservation->items)->toHaveCount(3);

    booking(function () use ($reservation): void {
        expect(InventoryLock::query()->where('reservation_id', $reservation->id)->count())->toBe((3 + 3 + 1) * 3)
            ->and(InventoryLock::query()->where('lock_type', '!=', LockType::Reservation->value)->count())->toBe(0)
            ->and(ReservationItemNight::query()->count())->toBe(9)
            ->and($reservation->guests()->sole()->is_primary)->toBeTrue()
            ->and(ReservationItemNight::query()->where('stay_date', '2026-11-12')->sum('total_amount'))->toEqual('45540.00');
    });

    // Deposit due 30 minutes after booking (stored in UTC).
    expect((int) round(abs($reservation->deposit_due_at?->diffInMinutes(now()->addMinutes(30)) ?? 99)))->toBeLessThanOrEqual(1);
    Event::assertDispatched(ReservationCreated::class, fn (ReservationCreated $event): bool => $event->reservationId === $reservation->id);
});

it('refuses a room-night that is already taken and saves nothing', function (): void {
    booking(fn () => CreateReservation::make()->handle(newBooking([roomItem('701')], ['checkIn' => CarbonImmutable::parse('2026-11-12'), 'checkOut' => CarbonImmutable::parse('2026-11-14')])));

    $refused = null;

    try {
        booking(fn () => CreateReservation::make()->handle(newBooking([cottageItem('C08'), cottageItem('C07')])));
    } catch (RoomNoLongerAvailable $exception) {
        $refused = $exception;
    }

    expect($refused)->toBeInstanceOf(RoomNoLongerAvailable::class)
        ->and($refused?->taken)->toBe(['701' => ['2026-11-12']]);

    booking(fn () => expect(Reservation::query()->count())->toBe(1)->and(InventoryLock::query()->count())->toBe(2));
});

it('refuses doubled rooms, units without a rate and impossible bookings', function (): void {
    $ids = bookingIds();

    expect(fn () => booking(fn () => CreateReservation::make()->handle(newBooking([cottageItem('C07'), roomItem('702')]))))->toThrow(BookingNotPossible::class)
        ->and(fn () => booking(fn () => CreateReservation::make()->handle(newBooking([new BookingItem(ItemType::Cottage, $ids['cottages']['C04'], $ids['plan'], 2)]))))->toThrow(BookingNotPossible::class)
        ->and(fn () => booking(fn () => CreateReservation::make()->handle(newBooking([]))))->toThrow(BookingNotPossible::class)
        ->and(fn () => booking(fn () => CreateReservation::make()->handle(newBooking([roomItem('401')], ['checkIn' => CarbonImmutable::parse('2026-11-12'), 'checkOut' => CarbonImmutable::parse('2026-11-12')]))))->toThrow(BookingNotPossible::class);
});

it('uses a negotiated deposit, and needs the override to go below the minimum', function (): void {
    $half = booking(fn (): Reservation => CreateReservation::make()->handle(newBooking([roomItem('401')], ['depositPercent' => '50'])));
    expect($half->deposit_required)->toBe('11385.00')->and($half->deposit_override_by)->toBeNull();

    expect(fn () => booking(fn () => CreateReservation::make()->handle(newBooking([roomItem('402')], ['depositPercent' => '10']))))->toThrow(DepositBelowMinimum::class);

    $low = booking(fn (): Reservation => CreateReservation::make()->handle(newBooking([roomItem('402')], ['depositPercent' => '10', 'allowDepositOverride' => true, 'createdBy' => 42])));
    expect($low->deposit_required)->toBe('2277.00')->and($low->deposit_override_by)->toBe(42);
});

it('confirms at once when no deposit is due, and counts the promotion used', function (): void {
    $ids = bookingIds();
    booking(function () use ($ids): void {
        DepositPolicy::query()->update(['type' => 'none']);
        Promotion::factory()->create(['property_id' => $ids['property'], 'code' => 'SAVE10', 'discount_type' => 'percent', 'discount_value' => '10']);
    });

    $reservation = booking(fn (): Reservation => CreateReservation::make()->handle(newBooking([roomItem('401')], ['promoCode' => 'save10'])));

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->deposit_required)->toBe('0.00')
        ->and($reservation->discount_total)->toBe('1800.00')
        ->and($reservation->promo_code)->toBe('SAVE10')
        ->and(booking(fn (): int => Promotion::query()->value('times_used')))->toBe(1);
});
