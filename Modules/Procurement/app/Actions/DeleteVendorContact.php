<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Procurement\Models\VendorContact;

class DeleteVendorContact extends Action
{
    public function handle(VendorContact $contact): void
    {
        $contact->delete();
    }
}
