<?php

namespace Modules\Reservation\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Reservation\Database\Factories\QuoteItemFactory;
use Modules\Reservation\Enums\ItemType;

/**
 * One quoted unit with its price snapshot (room_id null for a whole cottage). Audited through its quote.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $quote_id
 * @property ItemType $item_type
 * @property int $cottage_id
 * @property int|null $room_id
 * @property int|null $room_type_id
 * @property int|null $cottage_type_id
 * @property int $rate_plan_id
 * @property string $unit_key
 * @property string $label
 * @property int $adults
 * @property int $children
 * @property string $subtotal
 * @property string $discount
 * @property string $tax
 * @property string $total
 * @property string $meal_component
 * @property int|null $promotion_id
 * @property string|null $promotion_code
 * @property string|null $promotion_name
 * @property string|null $promotion_amount
 * @property-read Collection<int, QuoteItemNight> $nights
 */
#[UseFactory(QuoteItemFactory::class)]
#[Fillable([
    'property_id', 'quote_id', 'item_type', 'cottage_id', 'room_id', 'room_type_id', 'cottage_type_id', 'rate_plan_id', 'unit_key', 'label',
    'adults', 'children', 'subtotal', 'discount', 'tax', 'total', 'meal_component', 'promotion_id', 'promotion_code', 'promotion_name', 'promotion_amount',
])]
class QuoteItem extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<QuoteItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_type' => ItemType::class,
            'adults' => 'integer',
            'children' => 'integer',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'meal_component' => 'decimal:2',
            'promotion_amount' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<QuoteItemNight, $this>
     */
    public function nights(): HasMany
    {
        return $this->hasMany(QuoteItemNight::class)->orderBy('stay_date');
    }
}
