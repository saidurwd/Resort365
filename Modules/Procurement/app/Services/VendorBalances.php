<?php

namespace Modules\Procurement\Services;

use Illuminate\Support\Facades\DB;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorPayment;

/**
 * What a vendor is owed and what has been paid ahead (Step 5.6): the unpaid balance of approved bills, and the part of
 * payments not yet applied to a bill.
 */
class VendorBalances
{
    public function payable(int $vendorId): string
    {
        return (string) VendorBill::query()->where('vendor_id', $vendorId)->whereIn('status', [VendorBillStatus::Approved->value, VendorBillStatus::Partial->value])->sum(DB::raw('total - paid_amount'));
    }

    public function advance(int $vendorId): string
    {
        return (string) VendorPayment::query()->where('vendor_id', $vendorId)->sum(DB::raw('amount - allocated_amount'));
    }
}
