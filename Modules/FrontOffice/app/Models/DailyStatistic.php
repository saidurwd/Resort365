<?php

namespace Modules\FrontOffice\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\FrontOffice\Database\Factories\DailyStatisticFactory;

/**
 * A property's day as the night audit closed it (ARCHITECTURE §5.7 step 7, §9.1 read-optimised
 * table): rooms, occupancy, ADR, RevPAR, room and package revenue, and the day's takings (charges
 * by category, payments by method). F&B covers and sales come with the restaurant (Phase 3).
 * Written once by the audit and never changed, so it is not activity-logged.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property Carbon $business_date
 * @property int $rooms_total
 * @property int $rooms_out_of_order
 * @property int $rooms_blocked
 * @property int $rooms_available
 * @property int $rooms_occupied
 * @property string $occupancy_percent
 * @property string $adr
 * @property string $revpar
 * @property string $room_revenue
 * @property string $package_meal_revenue
 * @property string $room_tax
 * @property string $charges_total
 * @property string $received_total
 * @property string $refunded_total
 * @property int $adults
 * @property int $children
 * @property int $arrivals
 * @property int $departures
 * @property int $no_shows
 * @property int $fnb_covers
 * @property string $fnb_sales
 * @property array<string, mixed> $takings
 */
#[UseFactory(DailyStatisticFactory::class)]
#[Fillable([
    'property_id', 'business_date', 'rooms_total', 'rooms_out_of_order', 'rooms_blocked', 'rooms_available', 'rooms_occupied', 'occupancy_percent',
    'adr', 'revpar', 'room_revenue', 'package_meal_revenue', 'room_tax', 'charges_total', 'received_total', 'refunded_total', 'adults', 'children',
    'arrivals', 'departures', 'no_shows', 'fnb_covers', 'fnb_sales', 'takings',
])]
class DailyStatistic extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<DailyStatisticFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'occupancy_percent' => 'decimal:2',
            'adr' => 'decimal:2',
            'revpar' => 'decimal:2',
            'room_revenue' => 'decimal:2',
            'package_meal_revenue' => 'decimal:2',
            'room_tax' => 'decimal:2',
            'charges_total' => 'decimal:2',
            'received_total' => 'decimal:2',
            'refunded_total' => 'decimal:2',
            'fnb_sales' => 'decimal:2',
            'takings' => 'array',
        ];
    }
}
