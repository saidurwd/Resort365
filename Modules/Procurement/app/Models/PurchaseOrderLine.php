<?php

namespace Modules\Procurement\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Procurement\Database\Factories\PurchaseOrderLineFactory;

/**
 * An ordered item with its price, tax and (from Step 5.6) the quantity received.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $purchase_order_id
 * @property int $item_id
 * @property string $quantity
 * @property string $unit_price
 * @property int|null $tax_category_id
 * @property string $net_amount
 * @property string $tax_amount
 * @property string $line_total
 * @property string $received_quantity
 * @property-read PurchaseOrder $order
 */
#[UseFactory(PurchaseOrderLineFactory::class)]
#[Fillable(['property_id', 'purchase_order_id', 'item_id', 'quantity', 'unit_price', 'tax_category_id', 'net_amount', 'tax_amount', 'line_total', 'received_quantity'])]
class PurchaseOrderLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PurchaseOrderLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2', 'received_quantity' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
