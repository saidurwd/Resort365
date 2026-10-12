<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Database\Factories\Concerns\ResolvesReferences;
use Modules\Procurement\Enums\VendorBillKind;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorBill;

/**
 * @extends Factory<VendorBill>
 */
class VendorBillFactory extends Factory
{
    use ResolvesReferences;

    protected $model = VendorBill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['property_id' => $this->propertyId(), 'bill_no' => 'VB-'.fake()->unique()->numerify('####-#####'), 'vendor_id' => Vendor::factory(), 'kind' => VendorBillKind::Service, 'status' => VendorBillStatus::Draft, 'bill_date' => now()->toDateString(), 'due_on' => now()->addDays(30)->toDateString(), 'currency' => 'BDT', 'subtotal' => '1000', 'tax_total' => '0', 'total' => '1000', 'paid_amount' => '0', 'created_by' => 1];
    }
}
