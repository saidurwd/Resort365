<?php

namespace Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\StockBalanceFactory;

/**
 * What a store holds of an item (Step 5.3): the quantity in the base unit and the weighted-average cost per unit. Kept in step with every movement.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $store_id
 * @property int $item_id
 * @property string $quantity
 * @property string $avg_cost
 * @property int|null $last_movement_id
 * @property-read Item $item
 * @property-read Store $store
 */
#[UseFactory(StockBalanceFactory::class)]
#[Fillable(['property_id', 'store_id', 'item_id', 'quantity', 'avg_cost', 'last_movement_id'])]
class StockBalance extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StockBalanceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'avg_cost' => 'decimal:4'];
    }

    /** The stock's value: quantity times average cost, to two decimals. */
    public function value(): string
    {
        return bcadd(bcmul((string) $this->quantity, (string) $this->avg_cost, 6), '0', 2);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
