<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Restaurant\Database\Factories\RecipeFactory;
use Modules\Restaurant\Enums\RecipeKind;

/**
 * The ingredients of one portion of a menu item, of one variant or modifier, or of a prepared component (sub-recipe), Step 5.7.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property RecipeKind $kind
 * @property int|null $menu_item_id
 * @property int|null $menu_item_variant_id
 * @property int|null $modifier_id
 * @property string|null $name
 * @property string $yield_quantity
 * @property string|null $yield_unit
 * @property string|null $notes
 * @property-read Collection<int, RecipeLine> $lines
 */
#[UseFactory(RecipeFactory::class)]
#[Fillable(['property_id', 'kind', 'menu_item_id', 'menu_item_variant_id', 'modifier_id', 'name', 'yield_quantity', 'yield_unit', 'notes'])]
class Recipe extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => RecipeKind::class, 'yield_quantity' => 'decimal:4'];
    }

    /**
     * @return HasMany<RecipeLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RecipeLine::class)->orderBy('id');
    }
}
