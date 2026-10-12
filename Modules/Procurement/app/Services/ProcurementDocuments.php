<?php

namespace Modules\Procurement\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\Vendor;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * The PDFs a vendor gets: a purchase order and a request for quotation (A4, dompdf).
 */
class ProcurementDocuments
{
    public function __construct(
        private readonly PropertyDirectory $properties,
        private readonly DepartmentDirectory $departments,
        private readonly ItemLabels $labels,
    ) {}

    public function purchaseOrder(PurchaseOrder $order): string
    {
        $order->loadMissing(['vendor', 'lines']);
        $items = $this->labels->for($order->lines->pluck('item_id'));

        return Pdf::loadView('procurement::documents.purchase-order', [
            'order' => $order, 'vendor' => $order->vendor, 'property' => $this->properties->find($order->property_id), 'items' => $items,
            'department' => $order->department_id !== null ? $this->departments->find($order->department_id)?->name : null,
        ])->setPaper('a4')->output();
    }

    public function rfq(Rfq $rfq, Vendor $vendor): string
    {
        $rfq->loadMissing('requisition.lines');

        return Pdf::loadView('procurement::documents.rfq', [
            'rfq' => $rfq, 'vendor' => $vendor, 'property' => $this->properties->find($rfq->property_id),
            'lines' => $rfq->requisition->lines, 'items' => $this->labels->for($rfq->requisition->lines->pluck('item_id')),
        ])->setPaper('a4')->output();
    }
}
