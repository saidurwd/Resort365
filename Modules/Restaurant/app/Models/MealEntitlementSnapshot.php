<?php

namespace Modules\Restaurant\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\MealEntitlementSnapshotFactory;
use Modules\Restaurant\Enums\MealPeriod;

/**
 * The covers a stay's meal plan included for a meal period on a business date, written by the night
 * audit (SnapshotMealEntitlements). A record of the day, so not activity-logged.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property Carbon $business_date
 * @property int $reservation_id
 * @property MealPeriod $meal_period
 * @property int $covers
 */
#[UseFactory(MealEntitlementSnapshotFactory::class)]
#[Fillable(['property_id', 'business_date', 'reservation_id', 'meal_period', 'covers'])]
class MealEntitlementSnapshot extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<MealEntitlementSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['business_date' => 'date', 'meal_period' => MealPeriod::class];
    }
}
