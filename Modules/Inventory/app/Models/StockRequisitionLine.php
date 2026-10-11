<?php

namespace Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\StockRequisitionLineFactory;

/**
 * An item and quantity on a requisition, and how much was issued (Step 5.4).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $stock_requisition_id
 * @property int $item_id
 * @property string $quantity
 * @property string $issued_quantity
 * @property-read Item $item
 */
#[UseFactory(StockRequisitionLineFactory::class)]
#[Fillable(['property_id', 'stock_requisition_id', 'item_id', 'quantity', 'issued_quantity'])]
class StockRequisitionLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StockRequisitionLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'issued_quantity' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<StockRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(StockRequisition::class, 'stock_requisition_id');
    }
}
