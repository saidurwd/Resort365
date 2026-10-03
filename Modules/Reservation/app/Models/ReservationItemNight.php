<?php

namespace Modules\Reservation\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Reservation\Database\Factories\ReservationItemNightFactory;

/**
 * The price snapshot of one night of an item (ARCHITECTURE §6.3): later rate changes never alter
 * it. net_amount = base + extras − discount; total_amount = net + tax.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $reservation_item_id
 * @property Carbon $stay_date
 * @property string $base_rate
 * @property string $extra_person_amount
 * @property string $meal_amount
 * @property string $discount
 * @property string $net_amount
 * @property string $tax_amount
 * @property string $total_amount
 * @property string|null $rate_source
 * @property Carbon|null $posted_to_folio_at
 */
#[UseFactory(ReservationItemNightFactory::class)]
#[Fillable([
    'property_id', 'reservation_item_id', 'stay_date', 'base_rate', 'extra_person_amount', 'meal_amount', 'discount', 'net_amount',
    'tax_amount', 'total_amount', 'rate_source', 'posted_to_folio_at',
])]
class ReservationItemNight extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ReservationItemNightFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stay_date' => 'date',
            'base_rate' => 'decimal:2',
            'extra_person_amount' => 'decimal:2',
            'meal_amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'posted_to_folio_at' => 'datetime',
        ];
    }
}
