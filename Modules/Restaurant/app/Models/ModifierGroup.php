<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Restaurant\Database\Factories\ModifierGroupFactory;

/**
 * A choice offered with menu items (ARCHITECTURE §5.10.2): "Cooking level" (required, choose 1) or
 * "Add-ons" (optional, up to 3). min_select > 0 makes it required.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $name
 * @property int $min_select
 * @property int $max_select
 */
#[UseFactory(ModifierGroupFactory::class)]
#[Fillable([
    'property_id', 'name', 'min_select', 'max_select',
])]
class ModifierGroup extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ModifierGroupFactory> */
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
     * @return HasMany<Modifier, $this>
     */
    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class)->orderBy('sort_order');
    }

    public function isRequired(): bool
    {
        return $this->min_select > 0;
    }
}
