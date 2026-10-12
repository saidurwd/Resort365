<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Enums\ExpenseClass;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorBillLine;

/**
 * @extends Factory<VendorBillLine>
 */
class VendorBillLineFactory extends Factory
{
    protected $model = VendorBillLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bill = VendorBill::factory()->create();

        return ['property_id' => $bill->property_id, 'vendor_bill_id' => $bill->id, 'description' => fake()->sentence(3), 'expense_class' => ExpenseClass::Utilities, 'quantity' => '1', 'unit_price' => '1000', 'net_amount' => '1000', 'tax_amount' => '0', 'line_total' => '1000', 'grni_amount' => '0'];
    }
}
