<?php

namespace Modules\Rates\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Property\Enums\UnitKind;
use Modules\Rates\Database\Factories\RateRestrictionFactory;

/**
 * Selling restrictions for a date: for one rate plan or all (null), and one room/cottage type or
 * all (null). Several rows for the same date combine (the strictest wins, see RateCalendar).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int|null $rate_plan_id
 * @property UnitKind|null $rateable_type
 * @property int|null $rateable_id
 * @property Carbon $date
 * @property int|null $min_stay
 * @property int|null $max_stay
 * @property bool $closed_to_arrival
 * @property bool $closed_to_departure
 * @property bool $stop_sell
 */
#[UseFactory(RateRestrictionFactory::class)]
#[Fillable(['property_id', 'rate_plan_id', 'rateable_type', 'rateable_id', 'date', 'min_stay', 'max_stay', 'closed_to_arrival', 'closed_to_departure', 'stop_sell'])]
class RateRestriction extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RateRestrictionFactory> */
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
            'date' => 'date',
            'min_stay' => 'integer',
            'max_stay' => 'integer',
            'closed_to_arrival' => 'boolean',
            'closed_to_departure' => 'boolean',
            'stop_sell' => 'boolean',
        ];
    }
}
