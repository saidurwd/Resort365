<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\DTOs\QuotedItem;
use Modules\Reservation\DTOs\ReservationChange;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Services\BookingQuoter;
use Modules\Reservation\Services\DepositConfirmation;
use Modules\Reservation\Services\ItemLabels;
use Modules\Reservation\Services\ReservationBalance;
use Modules\Reservation\Services\ReservationLogger;
use Modules\Reservation\Services\ReservationWriter;

/**
 * Changes a reservation's dates, rooms and cottages, rate plans or occupancy (ARCHITECTURE §6.7).
 * The new stay is priced first (BookingQuoter, with the booking's negotiated deposit percent and
 * promo code); then, in one transaction, the old items, nights and locks are deleted and the new
 * ones written. When another booking holds one of the new room-nights, the lock insert fails,
 * everything rolls back and the original booking is kept (RoomNoLongerAvailable).
 *
 * The deposit due time stays as it was; a tentative booking whose payments now cover the
 * (smaller) deposit is confirmed. Guests linked to a removed item stay on the booking.
 */
class ModifyReservation extends Action
{
    public function __construct(
        private readonly BookingQuoter $quoter,
        private readonly RateLookup $rates,
        private readonly ReservationWriter $writer,
        private readonly ReservationBalance $balance,
        private readonly DepositConfirmation $confirmation,
        private readonly ReservationLogger $logger,
        private readonly ItemLabels $labels,
    ) {}

    /**
     * The price of the new stay, without saving anything (the review screen).
     *
     * @throws BookingNotPossible
     */
    public function quote(Reservation $reservation, ReservationChange $change): BookingQuote
    {
        return $this->quoter->quote(new NewReservation(
            $reservation->property_id, $change->checkIn, $change->checkOut, $change->items, $reservation->primary_guest_id,
            $reservation->source, $reservation->company_id, $reservation->travel_agent_id, $reservation->deposit_percent,
            $reservation->promo_code, allowDepositOverride: true,
        ));
    }

    /**
     * @throws BookingNotPossible|ReservationNotChangeable|RoomNoLongerAvailable
     */
    public function handle(Reservation $reservation, ReservationChange $change, ?int $userId = null): Reservation
    {
        $this->ensureChangeable($reservation);
        $quote = $this->quote($reservation, $change);

        try {
            return $this->transaction(fn (): Reservation => $this->save($reservation->id, $change, $quote, $userId), attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw new RoomNoLongerAvailable($this->writer->takenRooms($reservation->property_id, $change->checkIn, $change->checkOut, $quote, $reservation->id));
        }
    }

    private function save(int $reservationId, ReservationChange $change, BookingQuote $quote, ?int $userId): Reservation
    {
        $reservation = Reservation::query()->with('items')->lockForUpdate()->findOrFail($reservationId);
        $this->ensureChangeable($reservation);

        $before = [
            'check_in' => $reservation->check_in->toDateString(), 'check_out' => $reservation->check_out->toDateString(),
            'grand_total' => $reservation->grand_total, 'deposit_required' => $reservation->deposit_required,
            'items' => $reservation->items->map(fn (ReservationItem $item): string => $this->labels->of($item))->implode(', '),
        ];

        // Guests stay on the booking; the item they were linked to goes away (the FK would delete them).
        $reservation->guests()->whereNotNull('reservation_item_id')->update(['reservation_item_id' => null]);
        $reservation->locks()->delete();
        $reservation->items()->delete();
        $this->writer->writeItems($reservation, $change->checkIn, $change->checkOut, $quote);

        $planId = $change->items[0]->ratePlanId;
        $reservation->forceFill([
            'check_in' => $change->checkIn->toDateString(),
            'check_out' => $change->checkOut->toDateString(),
            'rate_plan_id' => $planId,
            'adults' => array_sum(array_map(fn (BookingItem $item): int => $item->adults, $change->items)),
            'children' => array_sum(array_map(fn (BookingItem $item): int => $item->children, $change->items)),
            'subtotal' => $quote->subtotal,
            'discount_total' => $quote->discount,
            'tax_total' => $quote->tax,
            'grand_total' => $quote->total,
            'deposit_policy_id' => $quote->depositPolicy?->id,
            'deposit_percent' => $quote->deposit->percent,
            'deposit_required' => $quote->deposit->amount,
            'balance_due' => $this->balance->balance($quote->total, $reservation->amount_paid),
            'balance_due_on' => $quote->deposit->balanceDueOn,
            'payment_status' => $this->balance->status($quote->total, $reservation->amount_paid, $quote->deposit->amount),
            'cancellation_policy_id' => $this->rates->cancellationPolicyId($planId),
            'promotion_id' => $quote->promotion?->promotionId,
        ])->save();

        $after = [
            'check_in' => $change->checkIn->toDateString(), 'check_out' => $change->checkOut->toDateString(),
            'grand_total' => $quote->total, 'deposit_required' => $quote->deposit->amount,
            'items' => implode(', ', array_map(fn (QuotedItem $item): string => $item->label, $quote->items)),
        ];

        $this->logger->log($reservation, ReservationLogAction::Modified, __('Stay changed to :from → :to (:items); total :old → :new.', [
            'from' => $after['check_in'], 'to' => $after['check_out'], 'items' => $after['items'], 'old' => $before['grand_total'], 'new' => $after['grand_total'],
        ]), $this->differences($before, $after), $userId);

        $this->confirmation->confirmIfMet($reservation, $userId);

        return $reservation->load('items');
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return array<string, array{0: string, 1: string}>
     */
    private function differences(array $before, array $after): array
    {
        $changes = [];

        foreach ($before as $key => $old) {
            if ($old !== $after[$key]) {
                $changes[$key] = [$old, $after[$key]];
            }
        }

        return $changes;
    }

    private function ensureChangeable(Reservation $reservation): void
    {
        if (! $reservation->isChangeable()) {
            throw new ReservationNotChangeable(__('Only tentative and confirmed bookings can be changed.'));
        }
    }
}
