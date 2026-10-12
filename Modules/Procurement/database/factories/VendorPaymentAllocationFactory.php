<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorPayment;
use Modules\Procurement\Models\VendorPaymentAllocation;

/**
 * @extends Factory<VendorPaymentAllocation>
 */
class VendorPaymentAllocationFactory extends Factory
{
    protected $model = VendorPaymentAllocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payment = VendorPayment::factory()->create();
        $bill = VendorBill::factory()->create(['property_id' => $payment->property_id, 'vendor_id' => $payment->vendor_id]);

        return ['property_id' => $payment->property_id, 'vendor_payment_id' => $payment->id, 'vendor_bill_id' => $bill->id, 'amount' => '500'];
    }
}
