<?php

namespace Modules\Inventory\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\ItemUnitConversionFactory;

/**
 * An item's own conversion (Step 5.2): 1 `unit` = `factor` base units, e.g. 1 box = 24 pcs.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $item_id
 * @property int $unit_id
 * @property string $factor
 * @property-read Unit $unit
 */
#[UseFactory(ItemUnitConversionFactory::class)]
#[Fillable(['item_id', 'unit_id', 'factor'])]
class ItemUnitConversion extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ItemUnitConversionFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['factor' => 'decimal:6'];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
