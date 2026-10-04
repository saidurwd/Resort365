<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\PaymentStatus;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationCreated;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\BookingQuoter;
use Modules\Reservation\Services\ReservationLogger;
use Modules\Reservation\Services\ReservationWriter;

/**
 * Creates a reservation (ARCHITECTURE §6.6). The booking is priced first (BookingQuoter); then, in
 * one transaction, the reservation number is taken, the reservation, its items, nightly snapshots
 * and primary guest are written, and every room-night is locked in one bulk insert. When another
 * booking took a room first, the unique (room_id, stay_date) index refuses the insert, everything
 * rolls back and RoomNoLongerAvailable says which rooms and dates. The database constraint
 * decides, never a "check then insert".
 *
 * A new reservation is Tentative until its deposit is paid (ApplyPayment), or Confirmed at once
 * when no deposit is due.
 */
class CreateReservation extends Action
{
    public function __construct(
        private readonly BookingQuoter $quoter,
        private readonly RateLookup $rates,
        private readonly DocumentNumbers $numbers,
        private readonly ReservationWriter $writer,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @param  BookingQuote|null  $quoted  prices agreed earlier (a converted quote) instead of pricing the booking now
     *
     * @throws BookingNotPossible|DepositBelowMinimum|RoomNoLongerAvailable
     */
    public function handle(NewReservation $data, ?BookingQuote $quoted = null): Reservation
    {
        $quote = $quoted ?? $this->quoter->quote($data);

        if (! $quote->depositWithinLimits && ! $data->allowDepositOverride) {
            throw new DepositBelowMinimum(__('A deposit of :percent% is outside the deposit policy. A manager can allow it.', ['percent' => (string) $data->depositPercent]));
        }

        try {
            return $this->transaction(fn (): Reservation => $this->save($data, $quote), attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw new RoomNoLongerAvailable($this->writer->takenRooms($data->propertyId, $data->checkIn, $data->checkOut, $quote));
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

        $this->writer->writeItems($reservation, $data->checkIn, $data->checkOut, $quote);
        $reservation->guests()->create(['property_id' => $data->propertyId, 'guest_id' => $data->primaryGuestId, 'is_primary' => true]);

        if ($quote->promotion instanceof PromotionDiscount) {
            $this->rates->usePromotion($quote->promotion->promotionId);
        }

        $this->logger->log($reservation, ReservationLogAction::Created, __(':source booking: :status, total :total, deposit :deposit.', [
            'source' => $data->source->label(), 'status' => $reservation->status->label(), 'total' => $quote->total, 'deposit' => $quote->deposit->amount,
        ]), userId: $data->createdBy);

        ReservationCreated::dispatch($reservation->tenant_id, $reservation->id);

        return $reservation->load('items');
    }
}
