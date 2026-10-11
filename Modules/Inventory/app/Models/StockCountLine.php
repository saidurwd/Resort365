<?php

namespace Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\StockCountLineFactory;

/**
 * An item on a count (Step 5.4): the quantity the system held when the count started, what was counted and the difference.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $stock_count_id
 * @property int $item_id
 * @property string $system_quantity
 * @property string $unit_cost
 * @property int $snapshot_movement_id
 * @property string|null $counted_quantity
 * @property string|null $variance_quantity
 * @property int|null $adjustment_movement_id
 * @property-read Item $item
 */
#[UseFactory(StockCountLineFactory::class)]
#[Fillable(['property_id', 'stock_count_id', 'item_id', 'system_quantity', 'unit_cost', 'snapshot_movement_id', 'counted_quantity', 'variance_quantity', 'adjustment_movement_id'])]
class StockCountLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StockCountLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['system_quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'counted_quantity' => 'decimal:4', 'variance_quantity' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<StockCount, $this>
     */
    public function count(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }
}
