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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Rates\Database\Factories\RatePlanFactory;
use Modules\Rates\Enums\MealPlan;

/**
 * A rate plan of a property (ARCHITECTURE §5.5). Amounts are decimal strings in the property's
 * currency; prices_include_tax says whether they include the tax category's taxes.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property MealPlan $meal_plan
 * @property string $meal_adult_amount
 * @property string $meal_child_amount
 * @property bool $is_refundable
 * @property bool $prices_include_tax
 * @property int|null $tax_category_id
 * @property int|null $deposit_policy_id
 * @property int|null $cancellation_policy_id
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property list<string>|null $channels
 * @property bool $is_active
 * @property int $sort_order
 */
#[UseFactory(RatePlanFactory::class)]
#[Fillable([
    'property_id', 'code', 'name', 'description', 'meal_plan', 'meal_adult_amount', 'meal_child_amount', 'is_refundable',
    'prices_include_tax', 'tax_category_id', 'deposit_policy_id', 'cancellation_policy_id', 'valid_from', 'valid_to', 'channels', 'is_active', 'sort_order',
])]
class RatePlan extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RatePlanFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meal_plan' => MealPlan::class,
            'meal_adult_amount' => 'decimal:2',
            'meal_child_amount' => 'decimal:2',
            'is_refundable' => 'boolean',
            'prices_include_tax' => 'boolean',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'channels' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Empty means the property's default deposit policy.
     *
     * @return BelongsTo<DepositPolicy, $this>
     */
    public function depositPolicy(): BelongsTo
    {
        return $this->belongsTo(DepositPolicy::class)->withTrashed();
    }

    /**
     * Empty means the property's default cancellation policy.
     *
     * @return BelongsTo<CancellationPolicy, $this>
     */
    public function cancellationPolicy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class)->withTrashed();
    }

    /**
     * @return HasMany<Rate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class);
    }

    /**
     * Whether the plan can be sold for a stay night.
     */
    public function isValidOn(Carbon|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        return ($this->valid_from === null || $date->gte($this->valid_from)) && ($this->valid_to === null || $date->lte($this->valid_to));
    }
}
