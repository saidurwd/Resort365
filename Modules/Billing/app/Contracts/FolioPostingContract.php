<?php

namespace Modules\Billing\Contracts;

use Modules\Billing\DTOs\ChargeableStay;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\DTOs\FolioPosting;
use Modules\Billing\Exceptions\ChargeRejected;

/**
 * How other modules (Restaurant, later spa or laundry) post a charge to an in-house guest's folio
 * (ARCHITECTURE §5.9). The charge is refused (ChargeRejected, with the reason) when the guest is
 * not checked in, the folio is not open, or the folio's credit limit would be exceeded.
 */
interface FolioPostingContract
{
    /**
     * @throws ChargeRejected
     */
    public function postCharge(FolioCharge $charge): FolioPosting;

    /**
     * In-house bookings of a property an outlet could charge, found by room number, guest name or
     * booking code (all of them when the term is empty), at most 20.
     *
     * @return list<ChargeableStay>
     */
    public function chargeableStays(int $propertyId, ?string $term = null): array;

    /**
     * The id of an active charge code by its code (e.g. FNB), if there is one.
     */
    public function chargeCodeId(string $code): ?int;

    /**
     * Takes back a charge an outlet posted (its bill was voided): the folio line is voided.
     *
     * @throws ChargeRejected when the folio is closed (the guest checked out: a credit note is needed)
     */
    public function reverseCharge(int $folioLineId, string $reason, ?int $userId = null): void;
}
