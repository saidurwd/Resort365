<?php

namespace Modules\Website\Models;

use App\Support\Attachments\HasPhotos;
use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use App\Support\Translations\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Website\Database\Factories\WebsiteSiteFactory;
use Modules\Website\Enums\SiteTheme;
use Spatie\MediaLibrary\HasMedia;

/**
 * A property's public website (Step 8.2a, ARCHITECTURE §5.18): its web address (`/resort/{slug}`), whether it is public, its look
 * and its texts per language. Its photo gallery gives the hero (the first three photos) and the Gallery page.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $slug
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property SiteTheme $theme
 * @property string $name
 * @property array<string, string|null>|null $tagline
 * @property array<string, string|null>|null $intro_title
 * @property array<string, string|null>|null $intro_body
 * @property array<string, string|null>|null $highlights one highlight per line
 * @property array<string, string|null>|null $dining_intro
 * @property array<string, string|null>|null $house_rules one rule per line
 * @property array<string, string|null>|null $seo_title
 * @property array<string, string|null>|null $seo_description
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $email
 * @property string|null $address
 * @property string|null $map_url
 * @property string|null $facebook_url
 * @property string|null $instagram_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(WebsiteSiteFactory::class)]
#[Fillable(['property_id', 'slug', 'is_published', 'published_at', 'theme', 'name', 'tagline', 'intro_title', 'intro_body', 'highlights', 'dining_intro',
    'house_rules', 'seo_title', 'seo_description', 'phone', 'whatsapp', 'email', 'address', 'map_url', 'facebook_url', 'instagram_url'])]
class WebsiteSite extends Model implements HasMedia
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<WebsiteSiteFactory> */
    use HasFactory;

    use HasPhotos;
    use HasTranslations;
    use RecordsActivity;

    /** The texts stored per language. */
    public const array TEXTS = ['tagline', 'intro_title', 'intro_body', 'highlights', 'dining_intro', 'house_rules', 'seo_title', 'seo_description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'theme' => SiteTheme::class,
            ...array_fill_keys(self::TEXTS, 'array'),
        ];
    }

    /**
     * @return HasMany<WebsiteUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(WebsiteUnit::class);
    }

    /**
     * A per-line text (highlights, house rules) in the language, without empty lines.
     *
     * @return list<string>
     */
    public function lines(string $attribute, ?string $locale = null): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split('/\R/', $this->translated($attribute, $locale)) ?: []), fn (string $line): bool => $line !== ''));
    }
}
