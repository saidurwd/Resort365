<?php

namespace Modules\Procurement\Contracts;

use Modules\Procurement\DTOs\VendorSummary;

/**
 * Vendors for other modules (Accounting's ledger lines and reports), Step 5.6.
 */
interface VendorDirectory
{
    public function find(int $vendorId): ?VendorSummary;
}
