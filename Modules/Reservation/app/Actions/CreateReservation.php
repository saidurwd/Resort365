<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\RoomSummary;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\DTOs\QuotedItem;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Enums\PaymentStatus;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationCreated;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItemNight;
use Modules\Reservation\Services\BookingQuoter;

/**
 * Creates a reservation (ARCHITECTURE §6.6). The booking is priced first (BookingQuoter); then, in
 * one transaction, the reservation number is taken, the reservation, its items, nightly snapshots
 * and primary guest are written, and every room-night is locked in one bulk insert. When another
 * booking took a room first, the unique (room_id, stay_date) index refuses the insert, everything
 * rolls back and RoomNoLongerAvailable says which rooms and dates. The database constraint
 * decides, never a "check then insert".
 *
 * A new reservation is Tentative until its deposit is paid (Step 1.7), or Confirmed at once when
 * no deposit is due.
 */
class CreateReservation extends Action
{
    public function __construct(
        private readonly BookingQuoter $quoter,
        private readonly InventoryCatalog $catalog,
        private readonly RateLookup $rates,
        private readonly DocumentNumbers $numbers,
    ) {}

    /**
     * @throws BookingNotPossible|DepositBelowMinimum|RoomNoLongerAvailable
     */
    public function handle(NewReservation $data): Reservation
    {
        $quote = $this->quoter->quote($data);

        if (! $quote->depositWithinLimits && ! $data->allowDepositOverride) {
            throw new DepositBelowMinimum(__('A deposit of :percent% is outside the deposit policy. A manager can allow it.', ['percent' => (string) $data->depositPercent]));
        }

        try {
            return $this->transaction(fn (): Reservation => $this->save($data, $quote), attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw new RoomNoLongerAvailable($this->takenRooms($data, $quote));
        }
    }

    private function save(NewReservation $data, BookingQuote $quote): Reservation
    {
        $confirmed = BigDecimal::of($quote->deposit->amount)->isZero();
        $planId = $data->items[0]->ratePlanId;

        $reservation = Reservation::query()->create([
            'property_id' => $data->propertyId,
            'code' => $this->numbers->next('reservation', $data->propertyId),
            'status' => $confirmed ? ReservationStatus::Confirmed : ReservationStatus::Tentative,
            'payment_status' => PaymentStatus::Unpaid,
            'source' => $data->source,
            'primary_guest_id' => $data->primaryGuestId,
            'company_id' => $data->companyId,
            'travel_agent_id' => $data->travelAgentId,
            'rate_plan_id' => $planId,
            'check_in' => $data->checkIn->toDateString(),
            'check_out' => $data->checkOut->toDateString(),
            'adults' => array_sum(array_map(fn (BookingItem $item): int => $item->adults, $data->items)),
            'children' => array_sum(array_map(fn (BookingItem $item): int => $item->children, $data->items)),
            'currency_code' => $quote->currency,
            'subtotal' => $quote->subtotal,
            'discount_total' => $quote->discount,
            'tax_total' => $quote->tax,
            'grand_total' => $quote->total,
            'deposit_policy_id' => $quote->depositPolicy?->id,
            'deposit_percent' => $quote->deposit->percent,
            'deposit_required' => $quote->deposit->amount,
            'deposit_due_at' => $quote->deposit->dueAt !== null ? CarbonImmutable::parse($quote->deposit->dueAt)->utc() : null,
            'auto_cancel_unpaid' => $quote->deposit->autoCancelUnpaid,
            'deposit_override_by' => $quote->depositWithinLimits ? null : $data->createdBy,
            'balance_due' => $quote->total,
            'balance_due_on' => $quote->deposit->balanceDueOn,
            'cancellation_policy_id' => $this->rates->cancellationPolicyId($planId),
            'promo_code' => $quote->promotion->code ?? ($data->promoCode !== null && $data->promoCode !== '' ? strtoupper($data->promoCode) : null),
            'promotion_id' => $quote->promotion?->promotionId,
            'special_requests' => $data->specialRequests,
            'internal_notes' => $data->internalNotes,
            'created_by' => $data->createdBy,
        ]);

        if ($confirmed) {
            $reservation->forceFill(['confirmed_at' => now()])->save();
        }

        $now = now();
        $nights = [];
        $locks = [];

        foreach ($quote->items as $line) {
            $item = $this->createItem($reservation, $data, $line);
            $meals = count($line->quote->nights) > 0 ? bcdiv($line->quote->mealComponent, (string) count($line->quote->nights), 2) : '0.00';

            foreach ($line->quote->nights as $night) {
                $nights[] = [
                    'tenant_id' => $reservation->tenant_id, 'property_id' => $data->propertyId, 'reservation_item_id' => $item, 'stay_date' => $night->date,
                    'base_rate' => $night->base, 'extra_person_amount' => $night->extras, 'meal_amount' => $meals, 'discount' => $night->discount,
                    'net_amount' => $night->net, 'tax_amount' => $night->tax, 'total_amount' => $night->total, 'rate_source' => $night->source,
                    'created_at' => $now, 'updated_at' => $now,
                ];

                foreach ($line->roomIds as $roomId) {
                    $locks[] = [
                        'tenant_id' => $reservation->tenant_id, 'property_id' => $data->propertyId, 'room_id' => $roomId, 'stay_date' => $night->date,
                        'lock_type' => LockType::Reservation->value, 'reservation_id' => $reservation->id, 'reservation_item_id' => $item,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
        }

        ReservationItemNight::query()->insert($nights);
        $reservation->guests()->create(['property_id' => $data->propertyId, 'guest_id' => $data->primaryGuestId, 'is_primary' => true]);

        // One statement: the unique (room_id, stay_date) index decides who gets the rooms.
        InventoryLock::query()->insert($locks);

        if ($quote->promotion instanceof PromotionDiscount) {
            $this->rates->usePromotion($quote->promotion->promotionId);
        }

        ReservationCreated::dispatch($reservation->tenant_id, $reservation->id);

        return $reservation->load('items');
    }

    /**
     * @return int the item id
     */
    private function createItem(Reservation $reservation, NewReservation $data, QuotedItem $line): int
    {
        return $reservation->items()->create([
            'property_id' => $data->propertyId,
            'item_type' => $line->item->type,
            'cottage_id' => $line->cottageId,
            'room_id' => $line->item->type === ItemType::Room ? $line->item->unitId : null,
            'room_type_id' => $line->roomTypeId,
            'cottage_type_id' => $line->cottageTypeId,
            'rate_plan_id' => $line->item->ratePlanId,
            'check_in' => $data->checkIn->toDateString(),
            'check_out' => $data->checkOut->toDateString(),
            'adults' => $line->item->adults,
            'children' => $line->item->children,
            'status' => $reservation->status,
            'subtotal' => $line->quote->subtotal,
            'discount' => $line->quote->discount,
            'tax' => $line->quote->tax,
            'total' => $line->quote->total,
            'meal_component' => $line->quote->mealComponent,
        ])->id;
    }

    /**
     * The rooms and nights of this booking that another booking holds now.
     *
     * @return array<string, list<string>>
     */
    private function takenRooms(NewReservation $data, BookingQuote $quote): array
    {
        $roomIds = array_merge(...array_map(fn (QuotedItem $item): array => $item->roomIds, $quote->items));
        $dates = array_map(fn (CarbonInterface $date): string => $date->toDateString(), CarbonPeriod::create($data->checkIn, $data->checkOut->subDay())->toArray());
        $numbers = collect($this->catalog->rooms($data->propertyId))->mapWithKeys(fn (RoomSummary $room): array => [$room->id => $room->number]);

        $taken = InventoryLock::query()->whereIn('room_id', $roomIds)->whereIn('stay_date', $dates)->orderBy('stay_date')->get()
            ->groupBy(fn (InventoryLock $lock): string => (string) $numbers->get($lock->room_id, '#'.$lock->room_id))
            ->map(fn ($locks): array => $locks->map(fn (InventoryLock $lock): string => $lock->stay_date->toDateString())->values()->all())
            ->all();

        return $taken !== [] ? $taken : ['?' => $dates];
    }
}
