<?php

namespace Modules\Procurement\Services;

use Modules\Procurement\Contracts\VendorDirectory;
use Modules\Procurement\DTOs\VendorSummary;
use Modules\Procurement\Models\Vendor;

class VendorDirectoryService implements VendorDirectory
{
    public function find(int $vendorId): ?VendorSummary
    {
        $vendor = Vendor::query()->find($vendorId);

        return $vendor instanceof Vendor ? new VendorSummary($vendor->id, $vendor->code, $vendor->name) : null;
    }
}
