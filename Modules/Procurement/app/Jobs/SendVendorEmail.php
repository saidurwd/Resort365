<?php

namespace Modules\Procurement\Jobs;

use App\Support\Tenancy\InteractsWithTenant;
use App\Support\Tenancy\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Services\VendorEmailComposer;

/**
 * Sends a purchase order or an RFQ to a vendor in the background (emails and PDFs are queued), as the tenant that
 * dispatched it. $type is `purchase_order` (subject = order id) or `rfq` (subject = RFQ id, with the vendor id).
 */
class SendVendorEmail implements ShouldQueue, TenantAware
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithTenant;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $type,
        public readonly int $subjectId,
        public readonly string $to,
        public readonly ?int $vendorId = null,
    ) {}

    public function handle(VendorEmailComposer $composer): void
    {
        if ($this->type === 'rfq') {
            $rfq = Rfq::query()->find($this->subjectId);
            $vendor = Vendor::query()->find($this->vendorId);

            if ($rfq instanceof Rfq && $vendor instanceof Vendor) {
                $composer->sendRfq($rfq, $vendor, $this->to);
            }

            return;
        }

        $order = PurchaseOrder::query()->find($this->subjectId);

        if ($order instanceof PurchaseOrder) {
            $composer->sendPurchaseOrder($order, $this->to);
        }
    }
}
