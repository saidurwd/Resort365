<?php

namespace Modules\Property\Models;

use App\Support\Attachments\HasPhotos;
use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Property\Database\Factories\CottageFactory;
use Modules\Property\Enums\BookingMode;
use Modules\Property\Enums\CottageStatus;
use Spatie\MediaLibrary\HasMedia;

/**
 * A building of a property that holds one or more rooms (ARCHITECTURE §5.4, §6.1). A single-room
 * cottage is simply a cottage with one room.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $cottage_type_id
 * @property string $code
 * @property string $name
 * @property string|null $zone
 * @property BookingMode $booking_mode
 * @property int|null $max_occupancy_override
 * @property CottageStatus $status
 * @property int $sort_order
 * @property string|null $description
 * @property-read CottageType $cottageType
 * @property-read Collection<int, Room> $rooms
 */
#[UseFactory(CottageFactory::class)]
#[Fillable(['property_id', 'cottage_type_id', 'code', 'name', 'zone', 'booking_mode', 'max_occupancy_override', 'status', 'sort_order', 'description'])]
class Cottage extends Model implements HasMedia
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<CottageFactory> */
    use HasFactory;

    use HasPhotos;
    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_mode' => BookingMode::class,
            'status' => CottageStatus::class,
            'max_occupancy_override' => 'integer',
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
     * @return BelongsTo<CottageType, $this>
     */
    public function cottageType(): BelongsTo
    {
        return $this->belongsTo(CottageType::class)->withTrashed();
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class)->orderBy('sort_order')->orderBy('number');
    }

    public function isActive(): bool
    {
        return $this->status === CottageStatus::Active;
    }
}
