<?php

namespace Modules\Property\Models;

use App\Support\Attachments\HasPhotos;
use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Property\Database\Factories\RoomTypeFactory;
use Modules\Property\Support\HasAmenities;
use Spatie\MediaLibrary\HasMedia;

/**
 * A kind of room, e.g. "Deluxe King" (ARCHITECTURE §5.4). max_occupancy caps adults plus
 * children in every room of the type (OccupancyCalculator).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $base_occupancy
 * @property int $max_adults
 * @property int $max_children
 * @property int $max_occupancy
 * @property string|null $bed_configuration
 * @property string|null $size_sqm
 * @property bool $is_active
 * @property int $sort_order
 */
#[UseFactory(RoomTypeFactory::class)]
#[Fillable([
    'property_id', 'code', 'name', 'description', 'base_occupancy', 'max_adults', 'max_children', 'max_occupancy',
    'bed_configuration', 'size_sqm', 'is_active', 'sort_order',
])]
class RoomType extends Model implements HasAmenities, HasMedia
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RoomTypeFactory> */
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
            'base_occupancy' => 'integer',
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'max_occupancy' => 'integer',
            'size_sqm' => 'decimal:2',
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
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return MorphToMany<Amenity, $this>
     */
    public function amenities(): MorphToMany
    {
        return $this->morphToMany(Amenity::class, 'linkable', 'amenity_links')->orderBy('sort_order')->orderBy('name');
    }
}
