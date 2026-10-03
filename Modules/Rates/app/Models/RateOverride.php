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
use Modules\Rates\Database\Factories\RateOverrideFactory;

/**
 * A fixed price for one date (events, holidays), which beats every season and base rate.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $rate_plan_id
 * @property UnitKind $rateable_type
 * @property int $rateable_id
 * @property Carbon $date
 * @property string $amount
 */
#[UseFactory(RateOverrideFactory::class)]
#[Fillable(['property_id', 'rate_plan_id', 'rateable_type', 'rateable_id', 'date', 'amount'])]
class RateOverride extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RateOverrideFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['rateable_type' => UnitKind::class, 'rateable_id' => 'integer', 'date' => 'date', 'amount' => 'decimal:2'];
    }
}
