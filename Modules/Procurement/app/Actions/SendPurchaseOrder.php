<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Jobs\SendVendorEmail;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\VendorContact;

/**
 * Emails an approved purchase order to its vendor as a PDF (Step 5.5). The address is the one given, else the vendor's
 * primary contact, else the vendor's own; sending again is allowed. The email goes out through the queue.
 */
class SendPurchaseOrder extends Action
{
    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(PurchaseOrder $order, ?string $to = null): PurchaseOrder
    {
        $locked = $this->transaction(function () use ($order, $to): PurchaseOrder {
            $locked = PurchaseOrder::query()->lockForUpdate()->with('vendor')->findOrFail($order->id);

            if (! in_array($locked->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent], true)) {
                throw new ProcurementRuleViolated(__('Order :no is :status: only an approved order is sent.', ['no' => $locked->order_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $address = $to !== null && trim($to) !== '' ? trim($to) : (VendorContact::query()->where('vendor_id', $locked->vendor_id)->where('is_primary', true)->whereNotNull('email')->value('email') ?? $locked->vendor->email);

            if ($address === null || $address === '') {
                throw new ProcurementRuleViolated(__(':vendor has no email address: add one to the vendor first.', ['vendor' => $locked->vendor->name]));
            }

            $locked->forceFill(['status' => PurchaseOrderStatus::Sent, 'sent_at' => now(), 'sent_to' => $address])->save();

            return $locked;
        });

        SendVendorEmail::dispatch('purchase_order', $locked->id, (string) $locked->sent_to);

        return $locked;
    }
}
