<?php

namespace Modules\Accounting\Listeners;

use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\PostingService;
use Modules\Billing\Events\CityLedgerTransferred;
use Modules\Billing\Events\FolioChargeVoided;
use Modules\Billing\Events\InvoiceIssued;
use Modules\Billing\Events\PaymentReceived;
use Modules\Billing\Events\RefundIssued;
use Modules\FrontOffice\Events\NightAuditCompleted;
use Modules\Inventory\Events\StockAdjusted;
use Modules\Inventory\Events\StockIssued;
use Modules\Procurement\Events\GoodsReceived;
use Modules\Procurement\Events\PurchaseReturned;
use Modules\Procurement\Events\VendorBillApproved;
use Modules\Procurement\Events\VendorPaymentMade;
use Modules\Reservation\Events\ReservationCancelled;
use Modules\Restaurant\Events\PosSessionClosed;
use Modules\Restaurant\Events\RestaurantBillSettled;
use Modules\Restaurant\Events\RestaurantBillVoided;

/**
 * Turns the operational events into journal entries (ARCHITECTURE §7.1, Step 4.2). A posting the ledger
 * refuses (no open period, an account not mapped) is reported, never thrown at the person whose action
 * caused it; `php artisan accounting:backpost` posts what was missed once it is fixed.
 */
class PostLedgerEntries
{
    public function __construct(private readonly PostingService $posting) {}

    public function onPaymentReceived(PaymentReceived $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->payment($event->paymentId));
    }

    public function onRefundIssued(RefundIssued $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->payment($event->paymentId));
    }

    public function onNightAuditCompleted(NightAuditCompleted $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->nightRevenue($event->propertyId, $event->businessDate));
    }

    public function onInvoiceIssued(InvoiceIssued $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->invoice($event->invoiceId));
    }

    public function onCityLedgerTransferred(CityLedgerTransferred $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->cityLedgerTransfer($event->entryId));
    }

    public function onFolioChargeVoided(FolioChargeVoided $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->voidedCharge($event->folioLineId));
    }

    public function onReservationCancelled(ReservationCancelled $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->cancellationFee($event->reservationId));
    }

    public function onRestaurantBillSettled(RestaurantBillSettled $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->restaurantBill($event->billId));
    }

    public function onRestaurantBillVoided(RestaurantBillVoided $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->restaurantBillVoided($event->billId));
    }

    public function onPosSessionClosed(PosSessionClosed $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->sessionVariance($event->sessionId));
    }

    public function onStockIssued(StockIssued $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->stockIssue($event->issueId));
    }

    public function onStockAdjusted(StockAdjusted $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->stockAdjustment($event->sourceType, $event->sourceId));
    }

    public function onGoodsReceived(GoodsReceived $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->goodsReceipt($event->receiptId));
    }

    public function onVendorBillApproved(VendorBillApproved $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->vendorBill($event->billId));
    }

    public function onVendorPaymentMade(VendorPaymentMade $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->vendorPayment($event->paymentId));
    }

    public function onPurchaseReturned(PurchaseReturned $event): void
    {
        $this->safely(fn (): ?JournalEntry => $this->posting->purchaseReturn($event->returnId));
    }

    private function safely(callable $post): void
    {
        try {
            $post();
        } catch (AccountingRuleViolated $exception) {
            report($exception);
        }
    }
}
