<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Procurement\Models\VendorItem;

class DeleteVendorPrice extends Action
{
    public function handle(VendorItem $entry): void
    {
        $entry->delete();
    }
}
