<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Actions\CreateReservation;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Models\Reservation;

/**
 * Bookings for DemoSeeder, made with CreateReservation so their rooms are locked:
 * - Step 1.6: Sunset Villa (whole) for a family and room 501 for a couple, both tentative;
 * - Step 1.7: room 101 for John Smith, confirmed by a 30% card deposit (RecordPayment, with a
 *   receipt), and room 601 whose deposit was due an hour ago, so `php artisan
 *   reservation:expire-holds` (or the scheduler) cancels it and frees the room.
 */
final class DemoBookings
{
    public static function seed(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            if (Reservation::query()->where('property_id', $propertyId)->exists()) {
                return;
            }

            $plan = collect(app(RateLookup::class)->ratePlans($propertyId))->firstWhere('code', 'BB');
            $guest = app(GuestLookup::class)->search('01711000001')[0] ?? null;
            $couple = app(GuestLookup::class)->search('ayesha.siddique@example.com')[0] ?? null;
            $cottage = collect(app(InventoryCatalog::class)->cottages($propertyId))->firstWhere('code', 'C08');
            $room = collect(app(InventoryCatalog::class)->rooms($propertyId))->firstWhere('number', '501');

            if ($plan === null || $guest === null || $couple === null || $cottage === null || $room === null) {
                return;
            }

            $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();

            CreateReservation::make()->handle(new NewReservation($propertyId, $today->addDays(20), $today->addDays(23),
                [new BookingItem(ItemType::Cottage, $cottage->id, $plan->id, 6, 2)], $guest->id, ReservationSource::Phone,
                specialRequests: 'Airport pickup at 11:00, please.'));

            CreateReservation::make()->handle(new NewReservation($propertyId, $today->addDays(5), $today->addDays(7),
                [new BookingItem(ItemType::Room, $room->id, $plan->id, 2)], $couple->id, ReservationSource::Email, depositPercent: '50'));

            $rooms = collect(app(InventoryCatalog::class)->rooms($propertyId))->keyBy('number');
            $john = app(GuestLookup::class)->search('john.smith@example.co.uk')[0] ?? null;
            $walkIn = app(GuestLookup::class)->search('01710000005')[0] ?? null;

            if ($john !== null && $rooms->has('101')) {
                $confirmed = CreateReservation::make()->handle(new NewReservation($propertyId, $today->addDays(10), $today->addDays(13),
                    [new BookingItem(ItemType::Room, $rooms['101']->id, $plan->id, 2)], $john->id, ReservationSource::Online));
                RecordPayment::make()->handle(new NewPayment($confirmed->id, PaymentMethod::Card, $confirmed->deposit_required, 'VISA-4421'));
            }

            if ($walkIn !== null && $rooms->has('601')) {
                $overdue = CreateReservation::make()->handle(new NewReservation($propertyId, $today->addDays(3), $today->addDays(4),
                    [new BookingItem(ItemType::Room, $rooms['601']->id, $plan->id, 2)], $walkIn->id, ReservationSource::Phone));
                $overdue->forceFill(['deposit_due_at' => now()->subHour()])->save();
            }
        });
    }
}
