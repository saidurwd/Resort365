<?php

namespace Modules\FrontOffice\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Modules\Billing\Contracts\FolioSettlement;
use Modules\Billing\DTOs\InvoiceSummary;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\FrontOffice\Events\GuestCheckedOut;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\Exceptions\StayNotPossible;

/**
 * Checks a guest out (ARCHITECTURE §5.7): every remaining room night is posted, every folio must be
 * settled (paid, refunded or moved to the city ledger), then — in one transaction — the folios are
 * invoiced and closed, the booking is marked checked out (its rooms released) and GuestCheckedOut
 * goes out after commit.
 */
class CheckOutGuest extends Action
{
    public function __construct(
        private readonly StayOperations $stays,
        private readonly FolioSettlement $settlement,
        private readonly PostStayCharges $postCharges,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @return list<InvoiceSummary>
     *
     * @throws StayNotPossible|ChargeRejected
     */
    public function handle(int $reservationId, ?int $userId = null): array
    {
        $this->postCharges->handle($reservationId, $userId);

        if (! $this->settlement->isSettled($reservationId)) {
            throw new ChargeRejected(__('Settle every folio first: take payment, refund a credit or move the balance to the city ledger.'));
        }

        return $this->transaction(function () use ($reservationId, $userId): array {
            $roomIds = $this->stays->roomIds($reservationId);
            $reservation = $this->stays->checkOut($reservationId, $userId);
            $invoices = $this->settlement->invoiceAndClose($reservationId, $userId);

            GuestCheckedOut::dispatch($this->tenants->tenantOrFail(self::class)->id, $reservation->propertyId, $reservation->id, $roomIds,
                array_map(fn (InvoiceSummary $invoice): int => $invoice->id, $invoices));

            return $invoices;
        }, attempts: 3);
    }
}
