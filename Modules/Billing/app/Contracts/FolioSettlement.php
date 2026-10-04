<?php

namespace Modules\Billing\Contracts;

use Modules\Billing\DTOs\FolioSummary;
use Modules\Billing\DTOs\InvoiceSummary;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Reservation\DTOs\RoomNightCharge;

/**
 * Settlement for the front office (ARCHITECTURE §5.7, §5.9): a reservation's folios, posting its
 * room nights, whether everything is settled, and the invoices at check-out. FrontOffice drives
 * check-out through this contract; Billing does not depend on FrontOffice.
 */
interface FolioSettlement
{
    /**
     * @return list<FolioSummary>
     */
    public function folios(int $reservationId): array;

    /**
     * Posts room nights to the reservation's folios (routed like any room charge). A night already
     * on a folio is skipped, so posting is safe to repeat.
     *
     * @param  list<RoomNightCharge>  $nights
     * @return list<int> the ids of the nights now on a folio
     *
     * @throws ChargeRejected
     */
    public function postRoomNights(int $reservationId, array $nights, ?int $userId = null): array;

    /**
     * Whether every open folio of the reservation has a zero balance.
     */
    public function isSettled(int $reservationId): bool;

    /**
     * Issues an invoice for each folio with charges and closes all of the reservation's folios.
     *
     * @return list<InvoiceSummary>
     *
     * @throws ChargeRejected when a folio is not settled
     */
    public function invoiceAndClose(int $reservationId, ?int $userId = null): array;
}
