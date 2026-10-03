<?php

namespace Modules\Rates\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Property\Enums\UnitKind;
use Modules\Rates\Database\Factories\RateFactory;

/**
 * The nightly price of a room type or cottage type in a rate plan, for a season (null = base)
 * and a set of weekdays (dow_mask, see DaysOfWeek).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $rate_plan_id
 * @property UnitKind $rateable_type
 * @property int $rateable_id
 * @property int|null $season_id
 * @property int $dow_mask
 * @property string $amount
 * @property string $extra_adult_amount
 * @property string $extra_child_amount
 */
#[UseFactory(RateFactory::class)]
#[Fillable(['property_id', 'rate_plan_id', 'rateable_type', 'rateable_id', 'season_id', 'dow_mask', 'amount', 'extra_adult_amount', 'extra_child_amount'])]
class Rate extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RateFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rateable_type' => UnitKind::class,
            'rateable_id' => 'integer',
            'dow_mask' => 'integer',
            'amount' => 'decimal:2',
            'extra_adult_amount' => 'decimal:2',
            'extra_child_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<RatePlan, $this>
     */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
