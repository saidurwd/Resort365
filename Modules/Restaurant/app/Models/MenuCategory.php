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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Restaurant\Database\Factories\MenuCategoryFactory;
use Modules\Restaurant\Models\Concerns\HasTranslations;

/**
 * A menu category of a property (ARCHITECTURE §5.10.2), nested (Food → Mains → Seafood), with a colour for
 * its POS button. The name is multi-language.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int|null $parent_id
 * @property array<string, string> $name
 * @property string $colour
 * @property int $sort_order
 * @property bool $is_active
 */
#[UseFactory(MenuCategoryFactory::class)]
#[Fillable([
    'property_id', 'parent_id', 'name', 'colour', 'sort_order', 'is_active',
])]
class MenuCategory extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<MenuCategoryFactory> */
    use HasFactory;

    use HasTranslations;
    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }
}
