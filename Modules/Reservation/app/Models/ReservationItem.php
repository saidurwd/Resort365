<?php

namespace Modules\Reservation\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Reservation\Database\Factories\ReservationItemFactory;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * One booked unit: a room (room_id set) or a whole cottage (room_id null; every room of the
 * cottage is locked). Audited through its reservation.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $reservation_id
 * @property ItemType $item_type
 * @property int $cottage_id
 * @property int|null $room_id
 * @property int|null $room_type_id
 * @property int|null $cottage_type_id
 * @property int $rate_plan_id
 * @property Carbon $check_in
 * @property Carbon $check_out
 * @property int $adults
 * @property int $children
 * @property ReservationStatus $status
 * @property string $subtotal
 * @property string $discount
 * @property string $tax
 * @property string $total
 * @property string $meal_component
 * @property-read Collection<int, ReservationItemNight> $nights
 */
#[UseFactory(ReservationItemFactory::class)]
#[Fillable([
    'property_id', 'reservation_id', 'item_type', 'cottage_id', 'room_id', 'room_type_id', 'cottage_type_id', 'rate_plan_id', 'check_in', 'check_out',
    'adults', 'children', 'status', 'subtotal', 'discount', 'tax', 'total', 'meal_component',
])]
class ReservationItem extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ReservationItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_type' => ItemType::class,
            'status' => ReservationStatus::class,
            'check_in' => 'date',
            'check_out' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'meal_component' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return HasMany<ReservationItemNight, $this>
     */
    public function nights(): HasMany
    {
        return $this->hasMany(ReservationItemNight::class)->orderBy('stay_date');
    }
}
