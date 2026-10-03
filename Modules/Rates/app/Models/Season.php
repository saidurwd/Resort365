<?php

namespace Modules\Rates\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Rates\Database\Factories\SeasonFactory;
use Modules\Rates\Enums\SeasonColor;

/**
 * A season of a property, e.g. Peak (ARCHITECTURE §5.5), over one or more periods. Where seasons
 * overlap, the higher priority wins.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $name
 * @property SeasonColor $color
 * @property int $priority
 * @property bool $is_active
 * @property-read Collection<int, SeasonPeriod> $periods
 */
#[UseFactory(SeasonFactory::class)]
#[Fillable(['property_id', 'name', 'color', 'priority', 'is_active'])]
class Season extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<SeasonFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['color' => SeasonColor::class, 'priority' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<SeasonPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(SeasonPeriod::class)->orderBy('start_date');
    }

    /**
     * @return HasMany<Rate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class);
    }
}
