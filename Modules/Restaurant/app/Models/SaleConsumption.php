<?php

namespace Modules\Restaurant\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\SaleConsumptionFactory;

/**
 * Stock a settled bill consumed (Step 5.7): one row per bill line and ingredient, with the cost it left stock at.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property int $pos_bill_id
 * @property int $pos_bill_line_id
 * @property int $item_id
 * @property int $store_id
 * @property string $quantity
 * @property string $unit_cost
 * @property string $total_cost
 * @property bool $is_complimentary
 * @property Carbon $business_date
 * @property int|null $stock_movement_id
 * @property int|null $reversal_movement_id
 * @property Carbon|null $reversed_at
 */
#[UseFactory(SaleConsumptionFactory::class)]
#[Fillable(['property_id', 'outlet_id', 'pos_bill_id', 'pos_bill_line_id', 'item_id', 'store_id', 'quantity', 'unit_cost', 'total_cost', 'is_complimentary', 'business_date', 'stock_movement_id', 'reversal_movement_id', 'reversed_at'])]
class SaleConsumption extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<SaleConsumptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2', 'is_complimentary' => 'boolean', 'business_date' => 'date', 'reversed_at' => 'datetime'];
    }
}
