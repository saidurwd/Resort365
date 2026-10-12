<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Procurement\DTOs\NewVendorPayment;
use Modules\Procurement\Events\VendorPaymentMade;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorPayment;

/**
 * Pays a vendor (Step 5.6), against one or more approved bills; what is not allocated is an advance that can be applied
 * to a later bill. Fires `VendorPaymentMade` for the ledger after commit.
 */
class RecordVendorPayment extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly AllocateVendorPayment $allocate,
    ) {}

    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(NewVendorPayment $data): VendorPayment
    {
        if (! BigDecimal::of($data->amount)->isPositive()) {
            throw new ProcurementRuleViolated(__('The amount must be more than zero.'));
        }

        $vendor = Vendor::query()->find($data->vendorId) ?? throw new ProcurementRuleViolated(__('Choose a vendor.'));

        return $this->transaction(function () use ($data, $vendor): VendorPayment {
            $payment = VendorPayment::query()->create([
                'property_id' => $data->propertyId, 'payment_no' => $this->numbers->next('vendor_payment'), 'vendor_id' => $vendor->id, 'method' => $data->method, 'paid_on' => $data->paidOn ?? now()->toDateString(),
                'amount' => (string) BigDecimal::of($data->amount)->toScale(2), 'allocated_amount' => '0', 'reference' => $data->reference, 'notes' => $data->notes, 'created_by' => $data->userId,
            ]);
            $payment = $this->allocate->handle($payment, $data->allocations);
            VendorPaymentMade::dispatch($payment->tenant_id, $payment->property_id, $payment->id);

            return $payment->load('allocations');
        }, attempts: 3);
    }
}
