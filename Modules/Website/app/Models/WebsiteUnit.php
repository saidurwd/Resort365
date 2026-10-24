<?php

namespace Modules\Website\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use App\Support\Translations\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Website\Database\Factories\WebsiteUnitFactory;

/**
 * How a room type or cottage type (`unit_key`, e.g. `room_type:4`) appears on a property's website (Step 8.2a): a headline and a
 * description per language (empty: the type's own description is shown), featured on the home page, or hidden.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $website_site_id
 * @property string $unit_key
 * @property array<string, string|null>|null $headline
 * @property array<string, string|null>|null $description
 * @property bool $is_featured
 * @property bool $is_hidden
 * @property int $sort_order
 */
#[UseFactory(WebsiteUnitFactory::class)]
#[Fillable(['property_id', 'website_site_id', 'unit_key', 'headline', 'description', 'is_featured', 'is_hidden', 'sort_order'])]
class WebsiteUnit extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<WebsiteUnitFactory> */
    use HasFactory;

    use HasTranslations;
    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['headline' => 'array', 'description' => 'array', 'is_featured' => 'boolean', 'is_hidden' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<WebsiteSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(WebsiteSite::class, 'website_site_id');
    }
}
