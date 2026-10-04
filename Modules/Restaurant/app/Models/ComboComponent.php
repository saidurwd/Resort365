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
use Modules\Restaurant\Database\Factories\ComboComponentFactory;

/**
 * An item (or one of its variants) that is part of a combo or set menu, with how many.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $menu_item_id
 * @property int $component_item_id
 * @property int|null $component_variant_id
 * @property int $quantity
 * @property int $sort_order
 */
#[UseFactory(ComboComponentFactory::class)]
#[Fillable([
    'property_id', 'menu_item_id', 'component_item_id', 'component_variant_id', 'quantity', 'sort_order',
])]
class ComboComponent extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ComboComponentFactory> */
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
        return $this->belongsTo(MenuItem::class, 'component_item_id');
    }
}
