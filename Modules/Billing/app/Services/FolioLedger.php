<?php

namespace Modules\Billing\Services;

use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Enums\FolioStatus;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Models\FolioRoutingRule;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;

/**
 * A reservation's folios: its guest folio (opened with the booking, or on first use for older
 * bookings), the folio a charge category is routed to, and balances kept in step with the lines.
 * Called inside the caller's transaction.
 */
class FolioLedger
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly GuestLookup $guests,
        private readonly PropertyDirectory $properties,
        private readonly DocumentNumbers $numbers,
        private readonly FolioMath $math,
    ) {}

    /**
     * @throws ChargeRejected for an unknown reservation
     */
    public function reservation(int $reservationId): ReservationSummary
    {
        return $this->reservations->find($reservationId) ?? throw new ChargeRejected(__('Unknown reservation.'));
    }

    public function guestFolio(int $reservationId): Folio
    {
        $existing = Folio::query()->where('reservation_id', $reservationId)->where('type', FolioType::Guest->value)->orderBy('id')->first();

        if ($existing instanceof Folio) {
            return $existing;
        }

        $reservation = $this->reservation($reservationId);

        return $this->open($reservation, FolioType::Guest, BillTo::Guest, $reservation->primaryGuestId,
            $this->guests->find($reservation->primaryGuestId)->name ?? __('Guest'));
    }

    public function open(ReservationSummary $reservation, FolioType $type, BillTo $billTo, ?int $billToId, string $name): Folio
    {
        return Folio::query()->create([
            'property_id' => $reservation->propertyId,
            'reservation_id' => $reservation->id,
            'folio_no' => $this->numbers->next('folio', $reservation->propertyId),
            'type' => $type,
            'bill_to_type' => $billTo,
            'bill_to_id' => $billToId,
            'name' => $name,
            'status' => FolioStatus::Open,
            'currency_code' => $reservation->currencyCode,
            'balance' => '0.00',
        ]);
    }

    /**
     * The folio a charge of this category goes to: the routing rule's, else the guest folio.
     */
    public function folioFor(int $reservationId, ChargeCategory $category): Folio
    {
        $rule = FolioRoutingRule::query()->where('reservation_id', $reservationId)->where('category', $category->value)->first();
        $folio = $rule instanceof FolioRoutingRule ? Folio::query()->find($rule->target_folio_id) : null;

        return $folio instanceof Folio ? $folio : $this->guestFolio($reservationId);
    }

    /**
     * Recompute the folio's balance from its lines (after a posting or a void).
     */
    public function recalculate(Folio $folio): Folio
    {
        $lines = FolioLine::query()->where('folio_id', $folio->id)->get(['line_type', 'total', 'is_voided'])
            ->map(fn (FolioLine $line): array => ['type' => $line->line_type, 'total' => $line->total, 'voided' => $line->is_voided]);

        $folio->forceFill(['balance' => $this->math->balance($lines)])->save();

        return $folio;
    }

    /**
     * The property's business date, the posting date of new lines.
     */
    public function businessDate(int $propertyId): string
    {
        return $this->properties->find($propertyId)->businessDate ?? now()->toDateString();
    }
}
