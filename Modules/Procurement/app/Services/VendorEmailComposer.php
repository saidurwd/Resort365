<?php

namespace Modules\Procurement\Services;

use Illuminate\Support\Facades\Notification;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Notifications\VendorMessage;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Writes and sends the emails a vendor gets (Step 5.5): the template's wording filled with the order's or RFQ's details,
 * with its PDF attached. Templates are editable at Setup → Email templates.
 */
class VendorEmailComposer
{
    public const string ORDER = 'procurement.purchase_order';

    public const string RFQ = 'procurement.rfq';

    public function __construct(
        private readonly NotificationTemplates $templates,
        private readonly PropertyDirectory $properties,
        private readonly ProcurementDocuments $documents,
    ) {}

    public function sendPurchaseOrder(PurchaseOrder $order, string $to): void
    {
        $order->loadMissing('vendor');
        $property = $this->properties->find($order->property_id);
        $rendered = $this->templates->render(self::ORDER, 'mail', [
            'vendor' => $order->vendor->name, 'order_no' => $order->order_no, 'property' => $property->name ?? '', 'property_phone' => $property->phone ?? '', 'property_email' => $property->email ?? '',
            'currency' => $order->currency, 'total' => number_format((float) $order->total, 2), 'expected_on' => $order->expected_on?->format('d M Y') ?? __('as soon as possible'), 'payment_terms' => (string) $order->payment_terms_days,
        ]);

        Notification::route('mail', [$to => $order->vendor->name])->notifyNow(new VendorMessage((string) $rendered->subject, $rendered->body, $property->name ?? (string) config('app.name'), [$order->order_no.'.pdf' => $this->documents->purchaseOrder($order)]));
    }

    public function sendRfq(Rfq $rfq, Vendor $vendor, string $to): void
    {
        $property = $this->properties->find($rfq->property_id);
        $rendered = $this->templates->render(self::RFQ, 'mail', [
            'vendor' => $vendor->name, 'rfq_no' => $rfq->rfq_no, 'property' => $property->name ?? '', 'property_phone' => $property->phone ?? '', 'property_email' => $property->email ?? '',
            'due_on' => $rfq->due_on?->format('d M Y') ?? __('as soon as you can'),
        ]);

        Notification::route('mail', [$to => $vendor->name])->notifyNow(new VendorMessage((string) $rendered->subject, $rendered->body, $property->name ?? (string) config('app.name'), [$rfq->rfq_no.'.pdf' => $this->documents->rfq($rfq, $vendor)]));
    }
}
