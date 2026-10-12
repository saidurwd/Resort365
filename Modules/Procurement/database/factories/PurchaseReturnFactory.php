<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseReturn;

/**
 * @extends Factory<PurchaseReturn>
 */
class PurchaseReturnFactory extends Factory
{
    protected $model = PurchaseReturn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $receipt = GoodsReceipt::factory()->create();

        return ['property_id' => $receipt->property_id, 'return_no' => 'RTV-'.fake()->unique()->numerify('####-#####'), 'debit_note_no' => 'DN-'.fake()->unique()->numerify('####-#####'), 'goods_receipt_id' => $receipt->id, 'vendor_id' => $receipt->vendor_id, 'store_id' => 1, 'returned_on' => now()->toDateString(), 'reason' => 'Damaged on arrival', 'total_value' => '100', 'tax_value' => '0', 'created_by' => 1];
    }
}
