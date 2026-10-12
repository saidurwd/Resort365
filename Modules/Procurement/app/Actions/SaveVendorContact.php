<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorContact;

/**
 * Adds or changes a person at a vendor (Step 5.5). Only one contact is the primary one: the person RFQs and orders go to.
 */
class SaveVendorContact extends Action
{
    /**
     * @param  array{name: string, role?: string|null, email?: string|null, phone?: string|null, is_primary?: bool}  $data
     */
    public function handle(Vendor $vendor, ?VendorContact $contact, array $data): VendorContact
    {
        return $this->transaction(function () use ($vendor, $contact, $data): VendorContact {
            $primary = (bool) ($data['is_primary'] ?? false);

            if ($primary) {
                VendorContact::query()->where('vendor_id', $vendor->id)->update(['is_primary' => false]);
            }

            $values = ['name' => trim($data['name']), 'role' => $data['role'] ?? null, 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null, 'is_primary' => $primary];

            if ($contact instanceof VendorContact) {
                $contact->forceFill($values)->save();

                return $contact;
            }

            return VendorContact::query()->create(['vendor_id' => $vendor->id, ...$values]);
        });
    }
}
