<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\PackageRedemptionFactory;
use Modules\Restaurant\Enums\MealPeriod;

/**
 * Meals of a guest's meal plan taken at an outlet (ARCHITECTURE §5.10.9, §8.4): the booking (code and guest
 * kept for reports), the business date and meal period, the covers taken and how many were included, and
 * the order and bill they were on. cost_amount waits for recipes (Phase 5). Posts no revenue: the meal
 * component of the room rate is recognised at night audit.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property int $reservation_id
 * @property string $reservation_code
 * @property string $guest_name
 * @property Carbon $business_date
 * @property MealPeriod $meal_period
 * @property int $covers_adults
 * @property int $covers_children
 * @property int $entitled covers the plan included for that meal that day (in total)
 * @property int $pos_order_id
 * @property int|null $pos_bill_id
 * @property string|null $cost_amount
 * @property int|null $manager_approval_id
 * @property int|null $created_by
 */
#[UseFactory(PackageRedemptionFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'reservation_id', 'reservation_code', 'guest_name', 'business_date', 'meal_period', 'covers_adults', 'covers_children',
    'entitled', 'pos_order_id', 'pos_bill_id', 'cost_amount', 'manager_approval_id', 'created_by',
])]
class PackageRedemption extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PackageRedemptionFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'meal_period' => MealPeriod::class,
            'cost_amount' => 'decimal:2',
        ];
    }

    public function covers(): int
    {
        return $this->covers_adults + $this->covers_children;
    }

    /**
     * @return BelongsTo<PosOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }
}
