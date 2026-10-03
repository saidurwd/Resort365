<?php

namespace Modules\Rates\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Rates\Database\Factories\SeasonPeriodFactory;

/**
 * A date range of a season (both dates included). Changes are audited on the season (SaveSeason).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $season_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 */
#[UseFactory(SeasonPeriodFactory::class)]
#[Fillable(['property_id', 'season_id', 'start_date', 'end_date'])]
class SeasonPeriod extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<SeasonPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    /**
     * @return BelongsTo<Season, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
