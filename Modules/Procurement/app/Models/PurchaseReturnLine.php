<?php

namespace Modules\Procurement\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Procurement\Database\Factories\PurchaseReturnLineFactory;

/**
 * A returned item: its value, the part not yet billed (reverses the GRNI), the part billed (reduces the payable) and its tax.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $purchase_return_id
 * @property int $goods_receipt_line_id
 * @property int $item_id
 * @property string $quantity
 * @property string $unit_cost
 * @property string $value
 * @property string $grni_value
 * @property string $billed_value
 * @property string $tax_value
 * @property string $stock_cost
 * @property int|null $stock_movement_id
 * @property-read PurchaseReturn $purchaseReturn
 */
#[UseFactory(PurchaseReturnLineFactory::class)]
#[Fillable(['property_id', 'purchase_return_id', 'goods_receipt_line_id', 'item_id', 'quantity', 'unit_cost', 'value', 'grni_value', 'billed_value', 'tax_value', 'stock_cost', 'stock_movement_id'])]
class PurchaseReturnLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PurchaseReturnLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'value' => 'decimal:2', 'grni_value' => 'decimal:2', 'billed_value' => 'decimal:2', 'tax_value' => 'decimal:2', 'stock_cost' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<PurchaseReturn, $this>
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }
}
