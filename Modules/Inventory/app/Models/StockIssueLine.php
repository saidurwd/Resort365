<?php

namespace Modules\Inventory\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\StockIssueLineFactory;

/**
 * One item on a stock issue, with the average cost it left at (Step 5.4).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $stock_issue_id
 * @property int $item_id
 * @property string $quantity
 * @property string $unit_cost
 * @property string $total_cost
 * @property int|null $stock_movement_id
 * @property-read Item $item
 */
#[UseFactory(StockIssueLineFactory::class)]
#[Fillable(['property_id', 'stock_issue_id', 'item_id', 'quantity', 'unit_cost', 'total_cost', 'stock_movement_id'])]
class StockIssueLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StockIssueLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * @return BelongsTo<StockIssue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(StockIssue::class, 'stock_issue_id');
    }
}
