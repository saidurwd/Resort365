<?php

namespace Modules\Procurement\Contracts;

use Modules\Procurement\DTOs\ReceiptFact;
use Modules\Procurement\DTOs\ReturnFact;
use Modules\Procurement\DTOs\VendorBillFact;
use Modules\Procurement\DTOs\VendorPaymentFact;

/**
 * What Accounting reads from Procurement to post the ledger (Step 5.6). Facts only; Procurement never calls Accounting.
 */
interface ProcurementFacts
{
    public function receipt(int $receiptId): ?ReceiptFact;

    /**
     * An approved bill (null while it is a draft, pending or cancelled).
     */
    public function bill(int $billId): ?VendorBillFact;

    public function payment(int $paymentId): ?VendorPaymentFact;

    public function purchaseReturn(int $returnId): ?ReturnFact;

    /**
     * Ids of everything that can be posted so far, for back-posting.
     *
     * @return array{receipts: list<int>, bills: list<int>, payments: list<int>, returns: list<int>}
     */
    public function history(): array;
}
