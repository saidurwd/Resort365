<?php

namespace Modules\Inventory\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\ItemStoreLevelFactory;

/**
 * An item's par and reorder levels in one store (Step 5.2), overriding the item's default reorder level there.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $item_id
 * @property int $store_id
 * @property string|null $par_level
 * @property string|null $reorder_level
 * @property-read Store $store
 */
#[UseFactory(ItemStoreLevelFactory::class)]
#[Fillable(['item_id', 'store_id', 'par_level', 'reorder_level'])]
class ItemStoreLevel extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ItemStoreLevelFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['par_level' => 'decimal:4', 'reorder_level' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
