<?php

namespace Modules\Restaurant\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\KotFactory;
use Modules\Restaurant\Enums\KotStatus;
use Modules\Restaurant\Enums\KotType;

/**
 * A kitchen order ticket (ARCHITECTURE §5.10.6): what one Send (or one void) tells one station, numbered
 * per outlet and business date. Shown on the kitchen display (Step 3.5) and printed for stations
 * that print. A kitchen record, written once by SendOrder / VoidOrderLine, so not activity-logged.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property int $pos_order_id
 * @property int|null $kitchen_station_id
 * @property int $kot_no
 * @property Carbon $business_date
 * @property KotType $type
 * @property KotStatus $status
 * @property Carbon $fired_at
 * @property Carbon|null $printed_at
 * @property int|null $created_by
 */
#[UseFactory(KotFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'pos_order_id', 'kitchen_station_id', 'kot_no', 'business_date', 'type', 'status', 'fired_at', 'printed_at',
    'created_by',
])]
class Kot extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<KotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'type' => KotType::class,
            'status' => KotStatus::class,
            'fired_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<KotLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(KotLine::class);
    }

    /**
     * @return BelongsTo<PosOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    /**
     * @return BelongsTo<KitchenStation, $this>
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'kitchen_station_id');
    }
}
