<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Procurement\Enums\VendorStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\Vendor;

/**
 * Creates or changes a vendor (Step 5.5). A blank code takes the next `VND-0001`; codes are unique per tenant.
 */
class SaveVendor extends Action
{
    public function __construct(private readonly DocumentNumbers $numbers) {}

    /**
     * @param  array<string, mixed>  $data  code, name, tax_id, email, phone, address, currency, payment_terms_days, rating, status, bank_*, notes
     *
     * @throws ProcurementRuleViolated
     */
    public function handle(?Vendor $vendor, array $data): Vendor
    {
        $rating = isset($data['rating']) && $data['rating'] !== '' ? (int) $data['rating'] : null;

        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            throw new ProcurementRuleViolated(__('A rating is between 1 and 5.'));
        }

        return $this->transaction(function () use ($vendor, $data, $rating): Vendor {
            $code = strtoupper(trim((string) ($data['code'] ?? '')));

            if ($code === '') {
                $code = $vendor instanceof Vendor ? $vendor->code : $this->numbers->next('vendor');
            }

            if (Vendor::query()->where('code', $code)->when($vendor instanceof Vendor, fn ($query) => $query->whereKeyNot($vendor?->id))->exists()) {
                throw new ProcurementRuleViolated(__('Another vendor already has the code :code.', ['code' => $code]));
            }

            $values = [
                'code' => $code, 'name' => trim((string) $data['name']), 'tax_id' => $data['tax_id'] ?? null, 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null, 'currency' => strtoupper((string) ($data['currency'] ?? 'BDT')), 'payment_terms_days' => (int) ($data['payment_terms_days'] ?? 0),
                'rating' => $rating, 'status' => VendorStatus::tryFrom((string) ($data['status'] ?? 'active')) ?? VendorStatus::Active, 'bank_name' => $data['bank_name'] ?? null,
                'bank_account_name' => $data['bank_account_name'] ?? null, 'bank_account_no' => $data['bank_account_no'] ?? null, 'bank_branch' => $data['bank_branch'] ?? null, 'notes' => $data['notes'] ?? null,
            ];

            if ($vendor instanceof Vendor) {
                $vendor->forceFill($values)->save();

                return $vendor;
            }

            return Vendor::query()->create($values);
        }, attempts: 3);
    }
}
