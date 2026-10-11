<?php

namespace Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Inventory\Database\Factories\StockMovementFactory;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\StockRuleViolated;

/**
 * One line of the stock ledger (Step 5.3). It is never changed or deleted: a mistake is corrected by a reversal or an adjustment. `quantity` is signed, in the item's base unit.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $store_id
 * @property int|null $department_id
 * @property int $item_id
 * @property MovementType $movement_type
 * @property Carbon $movement_date
 * @property string $quantity
 * @property string $unit_cost
 * @property string $total_cost
 * @property string $quantity_after
 * @property string $avg_cost_after
 * @property string|null $reason
 * @property string|null $notes
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $source_event
 * @property string|null $idempotency_key
 * @property int|null $reverses_id
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read Item $item
 * @property-read Store $store
 */
#[UseFactory(StockMovementFactory::class)]
#[Fillable(['property_id', 'store_id', 'department_id', 'item_id', 'movement_type', 'movement_date', 'quantity', 'unit_cost', 'total_cost', 'quantity_after', 'avg_cost_after', 'reason', 'notes', 'source_type', 'source_id', 'source_event', 'idempotency_key', 'reverses_id', 'created_by'])]
class StockMovement extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn () => throw new StockRuleViolated(__('A stock movement cannot be changed: reverse it or adjust the stock.')));
        static::deleting(fn () => throw new StockRuleViolated(__('A stock movement cannot be deleted: reverse it or adjust the stock.')));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['movement_type' => MovementType::class, 'movement_date' => 'date', 'quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2', 'quantity_after' => 'decimal:4', 'avg_cost_after' => 'decimal:4'];
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
