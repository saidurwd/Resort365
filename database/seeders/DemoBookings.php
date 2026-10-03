<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
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
 * Bookings for DemoSeeder (Step 1.6), made with CreateReservation so their rooms are locked:
 * Sunset Villa (whole) for a family and room 501 for a couple, both tentative.
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
        });
    }
}
