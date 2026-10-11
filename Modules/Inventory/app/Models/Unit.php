<?php

namespace Modules\Inventory\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Inventory\Database\Factories\UnitFactory;
use Modules\Inventory\Enums\UnitKind;

/**
 * A unit of measure (Step 5.2). Units of one kind convert by `factor` (1 of this unit = factor × its base unit); a base unit has none.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property UnitKind $kind
 * @property int|null $base_unit_id
 * @property string $factor
 * @property bool $is_active
 * @property-read Unit|null $baseUnit
 */
#[UseFactory(UnitFactory::class)]
#[Fillable(['code', 'name', 'kind', 'base_unit_id', 'factor', 'is_active'])]
class Unit extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => UnitKind::class, 'factor' => 'decimal:6', 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'base_unit_id');
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_unit_id');
    }
}
