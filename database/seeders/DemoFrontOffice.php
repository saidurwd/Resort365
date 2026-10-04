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
use Modules\Reservation\Actions\SaveRoomingList;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Models\Reservation;

/**
 * Front-desk demo (Step 2.2) on the property's business date: a paid booking arriving today in
 * room 201, ready to check in, a guest in house in room 402 who leaves today, (Step 2.4) a 6-room
 * group arriving today with a master folio and a partly filled rooming list, and (Step 2.6) a
 * confirmed booking in room 703 that will not arrive: the night audit marks it as a no-show.
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

            // Step 2.6: confirmed and paid, but the guest never comes (the night audit's no-show).
            $noShowGuest = app(GuestLookup::class)->search('01710000040')[0] ?? null;

            if ($noShowGuest !== null && $rooms->has('703')) {
                $noShow = CreateReservation::make()->handle(new NewReservation($propertyId, $today, $today->addDay(),
                    [new BookingItem(ItemType::Room, $rooms['703']->id, $plan->id, 2)], $noShowGuest->id, ReservationSource::Email,
                    specialRequests: 'Flight from Dhaka, may be delayed.'));
                RecordPayment::make()->handle(new NewPayment($noShow->id, PaymentMethod::Card, $noShow->deposit_required, 'VISA-3391'));
            }

            // Step 2.4: a 6-room group arriving today, paid, its rooming list half filled in.
            $groupRooms = ['101', '301', '501', '502', '601', '602'];
            $organiser = app(GuestLookup::class)->search('01710000030')[0] ?? null;

            if ($organiser !== null && collect($groupRooms)->every(fn (string $number): bool => $rooms->has($number))) {
                $group = CreateReservation::make()->handle(new NewReservation($propertyId, $today, $today->addDays(2),
                    array_map(fn (string $number): BookingItem => new BookingItem(ItemType::Room, $rooms[$number]->id, $plan->id, 2), $groupRooms),
                    $organiser->id, ReservationSource::Corporate, groupName: 'Dhaka Bank offsite'));
                RecordPayment::make()->handle(new NewPayment($group->id, PaymentMethod::BankTransfer, $group->deposit_required, 'DBL-TT-4410'));

                $items = $group->items()->orderBy('id')->get();
                SaveRoomingList::make()->handle(Reservation::query()->findOrFail($group->id), [
                    $items[0]->id => ['new_name' => 'Tahmina Chowdhury'],
                    $items[1]->id => ['new_name' => 'Imran Hossain'],
                    $items[2]->id => ['new_name' => 'Farhana Islam'],
                ]);
            }
        });
    }
}
