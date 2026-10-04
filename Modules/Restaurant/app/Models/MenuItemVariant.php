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
use Modules\Restaurant\Database\Factories\MenuItemVariantFactory;

/**
 * A size or form of a menu item (Half / Full, Glass / Bottle), priced per outlet on its own.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $menu_item_id
 * @property string $name
 * @property int $sort_order
 */
#[UseFactory(MenuItemVariantFactory::class)]
#[Fillable([
    'property_id', 'menu_item_id', 'name', 'sort_order',
])]
class MenuItemVariant extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<MenuItemVariantFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
        ];
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }
}
