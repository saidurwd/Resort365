<?php

namespace Modules\Rates\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Rates\Database\Factories\PromotionFactory;
use Modules\Rates\DTOs\PromotionTerms;
use Modules\Rates\Enums\DiscountType;

/**
 * A discount with a promo code, or automatic (no code), e.g. a long-stay discount (ARCHITECTURE §5.5).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property DiscountType $discount_type
 * @property string $discount_value
 * @property Carbon|null $stay_from
 * @property Carbon|null $stay_to
 * @property Carbon|null $book_from
 * @property Carbon|null $book_to
 * @property int|null $min_nights
 * @property int|null $max_nights
 * @property int|null $min_advance_days
 * @property list<int>|null $rate_plan_ids
 * @property list<string>|null $unit_keys
 * @property int|null $usage_limit
 * @property int $times_used
 * @property bool $is_active
 */
#[UseFactory(PromotionFactory::class)]
#[Fillable([
    'property_id', 'code', 'name', 'description', 'discount_type', 'discount_value', 'stay_from', 'stay_to', 'book_from', 'book_to',
    'min_nights', 'max_nights', 'min_advance_days', 'rate_plan_ids', 'unit_keys', 'usage_limit', 'is_active',
])]
class Promotion extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'stay_from' => 'date',
            'stay_to' => 'date',
            'book_from' => 'date',
            'book_to' => 'date',
            'min_nights' => 'integer',
            'max_nights' => 'integer',
            'min_advance_days' => 'integer',
            'rate_plan_ids' => 'array',
            'unit_keys' => 'array',
            'usage_limit' => 'integer',
            'times_used' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function terms(): PromotionTerms
    {
        return new PromotionTerms(
            $this->id, $this->code, $this->name, $this->discount_type, $this->discount_value,
            $this->stay_from?->toDateString(), $this->stay_to?->toDateString(), $this->book_from?->toDateString(), $this->book_to?->toDateString(),
            $this->min_nights, $this->max_nights, $this->min_advance_days,
            array_map(intval(...), $this->rate_plan_ids ?? []), $this->unit_keys ?? [],
            $this->usage_limit, $this->times_used, $this->is_active,
        );
    }
}
