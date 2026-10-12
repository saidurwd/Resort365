<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Database\Factories\Concerns\ResolvesReferences;
use Modules\Procurement\Enums\VendorPaymentMethod;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorPayment;

/**
 * @extends Factory<VendorPayment>
 */
class VendorPaymentFactory extends Factory
{
    use ResolvesReferences;

    protected $model = VendorPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['property_id' => $this->propertyId(), 'payment_no' => 'VP-'.fake()->unique()->numerify('####-#####'), 'vendor_id' => Vendor::factory(), 'method' => VendorPaymentMethod::BankTransfer, 'paid_on' => now()->toDateString(), 'amount' => '1000', 'allocated_amount' => '0', 'created_by' => 1];
    }
}
