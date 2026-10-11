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
use Modules\Inventory\Database\Factories\ItemCategoryFactory;
use Modules\Inventory\Enums\StockClass;

/**
 * A group of items, in a tree (Step 5.2): Food → Dairy, Housekeeping → Linen.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $parent_id
 * @property string $name
 * @property StockClass|null $account_class
 * @property int $sort_order
 * @property bool $is_active
 * @property-read ItemCategory|null $parent
 */
#[UseFactory(ItemCategoryFactory::class)]
#[Fillable(['parent_id', 'name', 'account_class', 'sort_order', 'is_active'])]
class ItemCategory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ItemCategoryFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'account_class' => StockClass::class];
    }

    /**
     * @return BelongsTo<ItemCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ItemCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /** The class of the category, else of the nearest parent that has one, else operating supplies. */
    public function stockClass(): StockClass
    {
        for ($category = $this, $guard = 0; $category instanceof self && $guard < 20; $category = $category->parent, $guard++) {
            if ($category->account_class instanceof StockClass) {
                return $category->account_class;
            }
        }

        return StockClass::Operating;
    }

    /** "Food › Dairy" */
    public function path(): string
    {
        return $this->parent instanceof self ? $this->parent->path().' › '.$this->name : $this->name;
    }
}
