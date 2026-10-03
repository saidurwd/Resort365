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
use Modules\Property\Database\Factories\CottageTypeFactory;
use Modules\Property\Support\HasAmenities;
use Spatie\MediaLibrary\HasMedia;

/**
 * A kind of cottage, e.g. "Family Villa" (ARCHITECTURE §5.4). Used for display and, from
 * Step 1.3, whole-cottage rates.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $max_occupancy
 * @property int $bedrooms
 * @property bool $is_active
 * @property int $sort_order
 */
#[UseFactory(CottageTypeFactory::class)]
#[Fillable(['property_id', 'code', 'name', 'description', 'max_occupancy', 'bedrooms', 'is_active', 'sort_order'])]
class CottageType extends Model implements HasAmenities, HasMedia
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<CottageTypeFactory> */
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
            'max_occupancy' => 'integer',
            'bedrooms' => 'integer',
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
     * @return HasMany<Cottage, $this>
     */
    public function cottages(): HasMany
    {
        return $this->hasMany(Cottage::class);
    }

    /**
     * @return MorphToMany<Amenity, $this>
     */
    public function amenities(): MorphToMany
    {
        return $this->morphToMany(Amenity::class, 'linkable', 'amenity_links')->orderBy('sort_order')->orderBy('name');
    }
}
