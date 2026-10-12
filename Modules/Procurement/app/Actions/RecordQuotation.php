<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Procurement\Enums\QuotationStatus;
use Modules\Procurement\Enums\RfqStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\RfqVendor;
use Modules\Procurement\Models\VendorQuotation;
use Modules\Procurement\Models\VendorQuotationLine;
use Modules\Procurement\Services\PurchaseTotals;

/**
 * Enters a vendor's answer to an RFQ (Step 5.5). The quantities are the requisition's; the vendor gives a unit price
 * (and a lead time) per item. Entering it again replaces the earlier answer.
 */
class RecordQuotation extends Action
{
    public function __construct(private readonly PurchaseTotals $totals) {}

    /**
     * @param  list<array{item_id: int, unit_price: string, lead_days?: int|null}>  $prices
     *
     * @throws ProcurementRuleViolated
     */
    public function handle(Rfq $rfq, int $vendorId, array $prices, ?string $reference = null, ?string $quotedOn = null, ?string $validUntil = null, ?string $notes = null): VendorQuotation
    {
        $rfq->loadMissing('requisition.lines');
        $asked = $rfq->requisition->lines->mapWithKeys(fn ($line): array => [$line->item_id => (string) $line->quantity]);

        $lines = [];

        foreach ($prices as $price) {
            if ($price['unit_price'] === '' || ! BigDecimal::of($price['unit_price'])->isPositive()) {
                continue;
            }

            if (! $asked->has($price['item_id'])) {
                throw new ProcurementRuleViolated(__('The RFQ does not ask for that item.'));
            }

            $lines[$price['item_id']] = ['unit_price' => $price['unit_price'], 'lead_days' => $price['lead_days'] ?? null];
        }

        if ($lines === []) {
            throw new ProcurementRuleViolated(__('Enter at least one price.'));
        }

        return $this->transaction(function () use ($rfq, $vendorId, $lines, $asked, $reference, $quotedOn, $validUntil, $notes): VendorQuotation {
            $locked = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);

            if ($locked->status !== RfqStatus::Sent) {
                throw new ProcurementRuleViolated(__('RFQ :no is :status: quotations are entered while it is out with the vendors.', ['no' => $locked->rfq_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            if (! RfqVendor::query()->where('rfq_id', $locked->id)->where('vendor_id', $vendorId)->exists()) {
                throw new ProcurementRuleViolated(__('That vendor was not asked to quote.'));
            }

            VendorQuotation::query()->where('rfq_id', $locked->id)->where('vendor_id', $vendorId)->delete();
            $total = BigDecimal::zero();

            foreach ($lines as $itemId => $line) {
                $total = $total->plus($this->totals->net($asked[$itemId], $line['unit_price']));
            }

            $quotation = VendorQuotation::query()->create([
                'property_id' => $locked->property_id, 'rfq_id' => $locked->id, 'vendor_id' => $vendorId, 'reference' => $reference, 'quoted_on' => $quotedOn ?? now()->toDateString(),
                'valid_until' => $validUntil, 'total' => (string) $total->toScale(2), 'status' => QuotationStatus::Received, 'notes' => $notes,
            ]);

            foreach ($lines as $itemId => $line) {
                VendorQuotationLine::query()->create(['property_id' => $locked->property_id, 'vendor_quotation_id' => $quotation->id, 'item_id' => $itemId, 'quantity' => $asked[$itemId], 'unit_price' => $line['unit_price'], 'lead_days' => $line['lead_days']]);
            }

            return $quotation->load('lines');
        }, attempts: 3);
    }
}
