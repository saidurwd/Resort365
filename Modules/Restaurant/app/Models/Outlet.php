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
use Modules\Restaurant\Database\Factories\OutletFactory;
use Modules\Restaurant\Enums\OutletType;

/**
 * A food and beverage outlet of a property (ARCHITECTURE §5.10.1): restaurant, bar, café, room service or
 * mini-bar, with its opening hours, tax setting and receipt texts. Stations, terminals, dining areas
 * and tables belong to it; staff work in the outlets assigned to them (outlet_user).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $code
 * @property string $name
 * @property OutletType $type
 * @property bool $prices_include_tax
 * @property int|null $default_tax_category_id
 * @property string $bill_prefix
 * @property string|null $receipt_header
 * @property string|null $receipt_footer
 * @property array<string, array{open: string|null, close: string|null}>|null $opening_hours
 * @property bool $is_active
 * @property int $sort_order
 */
#[UseFactory(OutletFactory::class)]
#[Fillable([
    'property_id', 'code', 'name', 'type', 'prices_include_tax', 'default_tax_category_id', 'bill_prefix', 'receipt_header', 'receipt_footer',
    'opening_hours', 'is_active', 'sort_order',
])]
class Outlet extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<OutletFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OutletType::class,
            'prices_include_tax' => 'boolean',
            'opening_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<KitchenStation, $this>
     */
    public function stations(): HasMany
    {
        return $this->hasMany(KitchenStation::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<PosTerminal, $this>
     */
    public function terminals(): HasMany
    {
        return $this->hasMany(PosTerminal::class)->orderBy('name');
    }

    /**
     * @return HasMany<DiningArea, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(DiningArea::class)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<DiningTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }
}
