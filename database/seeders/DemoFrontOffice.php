<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\DTOs\NewPayment;
use Modules\Billing\Enums\PaymentMethod;
use Modules\FrontOffice\Actions\CheckInGuest;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Actions\CreateReservation;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationSource;

/**
 * Front-desk demo (Step 2.2) on the property's business date: a paid booking arriving today in
 * room 201, ready to check in, and a guest in house in room 402 who leaves today.
 */
final class DemoFrontOffice
{
    public static function seed(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            // Seeding twice keeps the first run's stays.
            if (app(ReservationLookup::class)->inHouse($propertyId) !== []) {
                return;
            }

            $today = CarbonImmutable::parse(app(PropertyDirectory::class)->find($propertyId)->businessDate ?? now()->toDateString());
            $plan = collect(app(RateLookup::class)->ratePlans($propertyId))->firstWhere('code', 'BB');
            $rooms = collect(app(InventoryCatalog::class)->rooms($propertyId))->keyBy('number');
            $arriving = app(GuestLookup::class)->search('01710000010')[0] ?? null;
            $staying = app(GuestLookup::class)->search('01710000020')[0] ?? null;

            if ($plan === null || $arriving === null || $staying === null || ! $rooms->has('201') || ! $rooms->has('402')) {
                return;
            }

            $arrival = CreateReservation::make()->handle(new NewReservation($propertyId, $today, $today->addDays(2),
                [new BookingItem(ItemType::Room, $rooms['201']->id, $plan->id, 2)], $arriving->id, ReservationSource::Phone, specialRequests: 'Late arrival, around 22:00.'));
            RecordPayment::make()->handle(new NewPayment($arrival->id, PaymentMethod::MobileWallet, $arrival->deposit_required, 'BKASH-55120'));

            $inHouse = CreateReservation::make()->handle(new NewReservation($propertyId, $today->subDays(2), $today,
                [new BookingItem(ItemType::Room, $rooms['402']->id, $plan->id, 2)], $staying->id, ReservationSource::WalkIn));
            RecordPayment::make()->handle(new NewPayment($inHouse->id, PaymentMethod::Cash, $inHouse->deposit_required));
            CheckInGuest::make()->handle($inHouse->id);
        });
    }
}
