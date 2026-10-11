<?php

namespace Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\StockTransferLineFactory;

/**
 * An item and quantity on a transfer, with the average cost it left the source store at (Step 5.3).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $stock_transfer_id
 * @property int $item_id
 * @property string $quantity
 * @property string $unit_cost
 * @property-read Item $item
 */
#[UseFactory(StockTransferLineFactory::class)]
#[Fillable(['property_id', 'stock_transfer_id', 'item_id', 'quantity', 'unit_cost'])]
class StockTransferLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StockTransferLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<StockTransfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }
}
