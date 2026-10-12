<?php

namespace Modules\Procurement\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Procurement\Database\Factories\GoodsReceiptLineFactory;

/**
 * One order line received: accepted and rejected quantity, the cost it went into stock at, and what has been returned since.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $goods_receipt_id
 * @property int $purchase_order_line_id
 * @property int $item_id
 * @property string $accepted_quantity
 * @property string $rejected_quantity
 * @property string|null $reject_reason
 * @property string $unit_cost
 * @property string $value
 * @property string $returned_quantity
 * @property int|null $stock_movement_id
 * @property-read GoodsReceipt $receipt
 */
#[UseFactory(GoodsReceiptLineFactory::class)]
#[Fillable(['property_id', 'goods_receipt_id', 'purchase_order_line_id', 'item_id', 'accepted_quantity', 'rejected_quantity', 'reject_reason', 'unit_cost', 'value', 'returned_quantity', 'stock_movement_id'])]
class GoodsReceiptLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<GoodsReceiptLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['accepted_quantity' => 'decimal:4', 'rejected_quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'value' => 'decimal:2', 'returned_quantity' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }
}
