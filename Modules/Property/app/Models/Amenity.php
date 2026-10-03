<?php

namespace Modules\Property\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Property\Database\Factories\AmenityFactory;
use Modules\Property\Enums\AmenityCategory;

/**
 * An entry in the tenant's amenities catalogue (ARCHITECTURE §5.4), shared by all its properties
 * and linked to cottage types and room types through amenity_links.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $icon
 * @property AmenityCategory $category
 * @property bool $is_active
 * @property int $sort_order
 */
#[UseFactory(AmenityFactory::class)]
#[Fillable(['name', 'icon', 'category', 'is_active', 'sort_order'])]
class Amenity extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AmenityFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => AmenityCategory::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return MorphToMany<CottageType, $this>
     */
    public function cottageTypes(): MorphToMany
    {
        return $this->morphedByMany(CottageType::class, 'linkable', 'amenity_links');
    }

    /**
     * @return MorphToMany<RoomType, $this>
     */
    public function roomTypes(): MorphToMany
    {
        return $this->morphedByMany(RoomType::class, 'linkable', 'amenity_links');
    }
}
