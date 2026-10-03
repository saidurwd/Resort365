<?php

namespace Modules\Property\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Property\Database\Factories\RoomFactory;
use Modules\Property\Enums\HousekeepingStatus;
use Modules\Property\Enums\OccupancyStatus;

/**
 * The atomic unit of inventory (ARCHITECTURE §6.1). Every room belongs to exactly one cottage;
 * numbers are unique per property. Empty max_adults / max_children use the room type's values.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $cottage_id
 * @property int $room_type_id
 * @property string $number
 * @property string|null $name
 * @property string|null $floor
 * @property int|null $max_adults
 * @property int|null $max_children
 * @property HousekeepingStatus $housekeeping_status
 * @property OccupancyStatus $occupancy_status
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Cottage $cottage
 * @property-read RoomType $roomType
 */
#[UseFactory(RoomFactory::class)]
#[Fillable(['property_id', 'cottage_id', 'room_type_id', 'number', 'name', 'floor', 'max_adults', 'max_children', 'is_active', 'sort_order'])]
class Room extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'housekeeping_status' => 'clean',
        'occupancy_status' => 'vacant',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'housekeeping_status' => HousekeepingStatus::class,
            'occupancy_status' => OccupancyStatus::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Cottage, $this>
     */
    public function cottage(): BelongsTo
    {
        return $this->belongsTo(Cottage::class)->withTrashed();
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withTrashed();
    }
}
