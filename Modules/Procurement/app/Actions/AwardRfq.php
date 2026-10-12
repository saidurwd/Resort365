<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Procurement\DTOs\NewPurchaseOrder;
use Modules\Procurement\Enums\QuotationStatus;
use Modules\Procurement\Enums\RfqStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\VendorQuotation;

/**
 * Selects the winning quotation of an RFQ and turns it into a draft purchase order at the quoted prices (Step 5.5).
 * The other quotations are marked not selected, and the requisition becomes ordered.
 */
class AwardRfq extends Action
{
    public function __construct(private readonly SavePurchaseOrder $orders) {}

    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(Rfq $rfq, VendorQuotation $quotation, int $storeId, int $userId, ?string $expectedOn = null): PurchaseOrder
    {
        return $this->transaction(function () use ($rfq, $quotation, $storeId, $userId, $expectedOn): PurchaseOrder {
            $locked = Rfq::query()->lockForUpdate()->with('requisition')->findOrFail($rfq->id);

            if ($locked->status !== RfqStatus::Sent) {
                throw new ProcurementRuleViolated(__('RFQ :no is :status.', ['no' => $locked->rfq_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $winner = VendorQuotation::query()->with('lines')->where('rfq_id', $locked->id)->find($quotation->id) ?? throw new ProcurementRuleViolated(__('That quotation belongs to another RFQ.'));
            $asked = $locked->requisition->lines->pluck('item_id')->all();

            if (count($winner->lines) !== count($asked)) {
                throw new ProcurementRuleViolated(__('That quotation leaves out some items: choose one that quotes everything, or order the rest separately.'));
            }

            $lines = $winner->lines->map(fn ($line): array => ['item_id' => $line->item_id, 'quantity' => (string) $line->quantity, 'unit_price' => (string) $line->unit_price])->all();
            $order = $this->orders->handle(null, new NewPurchaseOrder($locked->property_id, $winner->vendor_id, $storeId, $lines, $userId, $locked->requisition->department_id, null, $expectedOn, $locked->requisition->notes, $locked->purchase_requisition_id, $winner->id));

            VendorQuotation::query()->where('rfq_id', $locked->id)->whereKeyNot($winner->id)->update(['status' => QuotationStatus::Rejected->value]);
            $winner->forceFill(['status' => QuotationStatus::Selected])->save();
            $locked->forceFill(['status' => RfqStatus::Awarded])->save();

            return $order;
        }, attempts: 3);
    }
}
