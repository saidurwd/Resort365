<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Restaurant\Database\Factories\OutletMenuItemFactory;

/**
 * What an outlet sells (ARCHITECTURE §8.4 outlet_menu_items): an item or one of its variants, its price
 * here, the outlet's station that makes it, whether it is sold out (86), whether a meal plan covers
 * it (Step 3.7) and the schedules it is sold in (none = whenever the outlet is open). variant_key is
 * the variant id or 0 (unique per outlet, item and variant).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property int $menu_item_id
 * @property int|null $menu_item_variant_id
 * @property int $variant_key
 * @property string $price
 * @property int|null $kitchen_station_id
 * @property bool $is_available
 * @property bool $is_package_eligible
 * @property list<int>|null $schedule_ids
 */
#[UseFactory(OutletMenuItemFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'menu_item_id', 'menu_item_variant_id', 'variant_key', 'price', 'kitchen_station_id', 'is_available', 'is_package_eligible',
    'schedule_ids',
])]
class OutletMenuItem extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<OutletMenuItemFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_package_eligible' => 'boolean',
            'schedule_ids' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    /**
     * @return BelongsTo<MenuItemVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariant::class, 'menu_item_variant_id');
    }
}
