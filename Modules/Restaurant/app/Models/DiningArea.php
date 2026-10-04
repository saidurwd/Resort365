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
use Modules\Restaurant\Database\Factories\DiningAreaFactory;

/**
 * A part of an outlet's floor (Indoor, Terrace, Pool deck…) with its own floor-plan canvas
 * (ARCHITECTURE §5.10.3).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property string $name
 * @property int $sort_order
 */
#[UseFactory(DiningAreaFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'name', 'sort_order',
])]
class DiningArea extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<DiningAreaFactory> */
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
     * @return HasMany<DiningTable, $this>
     */
    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('number');
    }
}
