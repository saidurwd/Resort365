<?php

namespace Modules\Restaurant\Models;

use App\Support\Attachments\HasPhotos;
use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Restaurant\Database\Factories\MenuItemFactory;
use Modules\Restaurant\Enums\Course;
use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Models\Concerns\HasTranslations;
use Spatie\MediaLibrary\HasMedia;

/**
 * A menu item of a property (ARCHITECTURE §5.10.2): code (PLU), multi-language name and description, course,
 * tax category (null = the outlet's), kind (dish, direct stock, open item, combo), dietary tags and
 * allergens; variants (Half / Full…), modifier groups and, for a combo, its components. What each
 * outlet sells it for, and at which station it is made, is in outlet_menu_items.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $menu_category_id
 * @property string $code
 * @property array<string, string> $name
 * @property array<string, string>|null $description
 * @property Course $course
 * @property int|null $tax_category_id
 * @property MenuItemKind $kind
 * @property int|null $inventory_item_id
 * @property list<string>|null $dietary_tags
 * @property list<string>|null $allergens
 * @property int $sort_order
 * @property bool $is_active
 */
#[UseFactory(MenuItemFactory::class)]
#[Fillable([
    'property_id', 'menu_category_id', 'code', 'name', 'description', 'course', 'tax_category_id', 'kind', 'inventory_item_id', 'dietary_tags',
    'allergens', 'sort_order', 'is_active',
])]
class MenuItem extends Model implements HasMedia
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    use HasPhotos;
    use HasTranslations;
    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'course' => Course::class,
            'kind' => MenuItemKind::class,
            'dietary_tags' => 'array',
            'allergens' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /**
     * @return HasMany<MenuItemVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MenuItemVariant::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<ModifierGroup, $this>
     */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'menu_item_modifier_groups')->withPivot(['tenant_id', 'sort_order'])->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<ComboComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(ComboComponent::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<OutletMenuItem, $this>
     */
    public function outletPrices(): HasMany
    {
        return $this->hasMany(OutletMenuItem::class);
    }
}
