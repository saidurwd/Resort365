<?php

namespace Modules\Restaurant\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Restaurant\Database\Factories\WastageLineFactory;

/**
 * An ingredient wasted, its quantity and cost.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $wastage_entry_id
 * @property int $item_id
 * @property string $quantity
 * @property string $unit_cost
 * @property string $total_cost
 * @property int|null $stock_movement_id
 */
#[UseFactory(WastageLineFactory::class)]
#[Fillable(['property_id', 'wastage_entry_id', 'item_id', 'quantity', 'unit_cost', 'total_cost', 'stock_movement_id'])]
class WastageLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<WastageLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2'];
    }
}
