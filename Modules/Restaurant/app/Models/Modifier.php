<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Restaurant\Database\Factories\ModifierFactory;

/**
 * One option of a modifier group, with the price it adds (may be 0).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $modifier_group_id
 * @property string $name
 * @property string $price_delta
 * @property int $sort_order
 * @property bool $is_active
 */
#[UseFactory(ModifierFactory::class)]
#[Fillable([
    'property_id', 'modifier_group_id', 'name', 'price_delta', 'sort_order', 'is_active',
])]
class Modifier extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ModifierFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
