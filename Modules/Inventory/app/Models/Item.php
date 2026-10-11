<?php

namespace Modules\Inventory\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\ItemFactory;

/**
 * An item of the catalogue (Step 5.2), shared by the tenant's properties. Quantities are in the base unit; purchase and issue units are optional.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $item_category_id
 * @property string $sku
 * @property string $name
 * @property string|null $barcode
 * @property int $base_unit_id
 * @property int|null $purchase_unit_id
 * @property int|null $issue_unit_id
 * @property bool $is_stockable
 * @property bool $track_batches
 * @property bool $track_expiry
 * @property string $reorder_level
 * @property string $reorder_qty
 * @property string|null $standard_cost
 * @property string|null $description
 * @property bool $is_active
 * @property-read ItemCategory $category
 * @property-read Unit $baseUnit
 */
#[UseFactory(ItemFactory::class)]
#[Fillable(['item_category_id', 'sku', 'name', 'barcode', 'base_unit_id', 'purchase_unit_id', 'issue_unit_id', 'is_stockable', 'track_batches', 'track_expiry', 'reorder_level', 'reorder_qty', 'standard_cost', 'description', 'is_active'])]
class Item extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_stockable' => 'boolean', 'track_batches' => 'boolean', 'track_expiry' => 'boolean', 'is_active' => 'boolean',
            'reorder_level' => 'decimal:4', 'reorder_qty' => 'decimal:4', 'standard_cost' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<ItemCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function issueUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'issue_unit_id');
    }

    /**
     * @return HasMany<ItemUnitConversion, $this>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(ItemUnitConversion::class);
    }

    /**
     * @return HasMany<ItemStoreLevel, $this>
     */
    public function levels(): HasMany
    {
        return $this->hasMany(ItemStoreLevel::class);
    }
}
