<?php

namespace Modules\Billing\Contracts;

use Modules\Billing\DTOs\ChargeFact;
use Modules\Billing\DTOs\CityLedgerTransferFact;
use Modules\Billing\DTOs\InvoiceFact;
use Modules\Billing\DTOs\PaymentFact;

/**
 * What Accounting reads from Billing to post the general ledger (ARCHITECTURE §7.1, Step 4.2). Facts
 * only; Billing never calls Accounting.
 */
interface LedgerFacts
{
    /**
     * Charges and adjustments posted on a business date that Billing itself earned, not yet voided.
     * Lines whose revenue another module posts (`revenue_posted_by_source`) are left out.
     *
     * @return list<ChargeFact>
     */
    public function chargesOn(int $propertyId, string $date): array;

    /**
     * One folio charge, voided or not (to reverse a posting after a void).
     */
    public function charge(int $folioLineId): ?ChargeFact;

    public function payment(int $paymentId): ?PaymentFact;

    public function invoice(int $invoiceId): ?InvoiceFact;

    public function cityLedgerTransfer(int $entryId): ?CityLedgerTransferFact;

    /**
     * Advance deposits a booking has paid (security deposits excluded).
     */
    public function depositsPaid(int $reservationId): string;

    /**
     * Charge codes with their category, for the account mapping screen.
     *
     * @return list<array{code: string, name: string, category: string}>
     */
    public function chargeCodes(): array;

    /**
     * Everything that has happened so far, for back-posting: ids of payments, invoices and city ledger
     * transfers, and the property dates with charges.
     *
     * @return array{payments: list<int>, invoices: list<int>, transfers: list<int>, charge_dates: list<array{property_id: int, date: string}>}
     */
    public function history(): array;
}
