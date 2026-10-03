<?php

/*
| Hold expiry (ARCHITECTURE §6.5 rule 4). Step 1.7 "Done when": an unpaid tentative booking cancels
| itself after the due time and its rooms become available.
*/

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Reservation\Actions\ApplyPayment;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationCancelled;
use Modules\Reservation\Jobs\ExpireTentativeHolds;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Services\AvailabilityService;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travel;

require_once __DIR__.'/../Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/*
| The job goes through every tenant in the database; tests that truncate instead of rolling back
| may leave other bookings behind, so these tests check their own bookings, not the overall count.
*/
function expireHolds(): int
{
    return (int) ExpireTentativeHolds::dispatchSync();
}

it('cancels an unpaid tentative booking after its deposit due time and frees its rooms', function (): void {
    Event::fake([ReservationCancelled::class]);
    $reservation = bookStay(['401']);
    expect($reservation->deposit_due_at?->isFuture())->toBeTrue();

    expireHolds();
    expect(freshReservation($reservation->id)->status)->toBe(ReservationStatus::Tentative);
    travel(31)->minutes();
    expect(expireHolds())->toBeGreaterThanOrEqual(1);

    $expired = freshReservation($reservation->id);
    expect($expired->status)->toBe(ReservationStatus::Cancelled)
        ->and($expired->cancellation_fee)->toBe('0.00')
        ->and($expired->items->pluck('status')->unique()->all())->toBe([ReservationStatus::Cancelled])
        ->and($expired->logs->first()?->action)->toBe(ReservationLogAction::Expired)
        ->and($expired->logs->first()?->user_id)->toBeNull()
        ->and(booking(fn (): int => InventoryLock::query()->where('reservation_id', $reservation->id)->count()))->toBe(0);

    // Room 401 is offered again for those nights.
    $ids = bookingIds();
    $search = new AvailabilitySearch($ids['property'], $ids['plan'], CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-13'), new Occupancy(2, 0));
    expect(booking(fn (): array => app(AvailabilityService::class)->lockedRoomIds($search)))->not->toContain($ids['rooms']['401']);

    Event::assertDispatched(ReservationCancelled::class, fn (ReservationCancelled $event): bool => $event->expired && $event->reservationId === $reservation->id);
});

it('leaves bookings alone that paid the deposit, are not due yet or do not auto-cancel', function (): void {
    $paid = bookStay(['401']);
    $partly = bookStay(['402']);
    $keep = bookStay(['701']);
    booking(fn () => ApplyPayment::make()->handle($paid->id, '6831.00', '6831.00'));
    booking(fn () => ApplyPayment::make()->handle($partly->id, '1000.00', '1000.00'));
    booking(fn () => freshReservation($keep->id)->forceFill(['auto_cancel_unpaid' => false])->save());

    travel(31)->minutes();
    expireHolds();

    expect(freshReservation($paid->id)->status)->toBe(ReservationStatus::Confirmed)
        ->and(freshReservation($partly->id)->status)->toBe(ReservationStatus::Cancelled)
        ->and(freshReservation($keep->id)->status)->toBe(ReservationStatus::Tentative);
});

it('expires holds in every tenant that may use the app', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
    $ours = bookStay(['401']);
    $theirs = bookStay(['401'], slug: 'greenvalley');
    Tenant::query()->where('slug', 'greenvalley')->update(['status' => 'suspended']);

    travel(31)->minutes();
    expireHolds();

    expect(freshReservation($ours->id)->status)->toBe(ReservationStatus::Cancelled)
        ->and(freshReservation($theirs->id, 'greenvalley')->status)->toBe(ReservationStatus::Tentative);
});

it('runs from the command and is scheduled every five minutes', function (): void {
    $reservation = bookStay(['401']);
    travel(31)->minutes();

    artisan('reservation:expire-holds')->expectsOutputToContain('Expired holds cancelled:')->assertSuccessful();
    expect(freshReservation($reservation->id)->status)->toBe(ReservationStatus::Cancelled);

    $event = collect(app(Schedule::class)->events())->first(fn (ScheduledEvent $event): bool => $event->description === 'reservation:expire-holds');
    expect($event?->expression)->toBe('*/5 * * * *');
});

it('shows the expired booking to other modules as cancelled', function (): void {
    $reservation = bookStay(['401']);
    travel(31)->minutes();
    expireHolds();

    $summary = app(TenantContext::class)->run(tenant('sunrise'), fn () => app(ReservationLookup::class)->find($reservation->id));
    expect($summary?->status)->toBe(ReservationStatus::Cancelled)
        ->and($summary?->acceptsPayments())->toBeFalse();
});
