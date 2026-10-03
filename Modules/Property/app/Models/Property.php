<?php

namespace Modules\Property\Models;

use App\Support\Attachments\HasAttachments;
use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Property\Database\Factories\PropertyFactory;
use Modules\Property\Enums\PropertyStatus;
use Modules\Property\Support\AccessiblePropertyScope;
use Spatie\MediaLibrary\HasMedia;

/**
 * A resort of the tenant (ARCHITECTURE §5.4). Signed-in users only see properties they may access.
 * The logo is stored in the "logo" media collection.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string $country_code
 * @property string $timezone
 * @property string $currency_code
 * @property string $check_in_time
 * @property string $check_out_time
 * @property Carbon $business_date
 * @property string|null $tax_registration_no
 * @property PropertyStatus $status
 */
#[UseFactory(PropertyFactory::class)]
#[Fillable([
    'code', 'name', 'legal_name', 'email', 'phone', 'address_line1', 'address_line2', 'city', 'state',
    'postal_code', 'country_code', 'timezone', 'currency_code', 'check_in_time', 'check_out_time',
    'business_date', 'tax_registration_no', 'status',
])]
class Property extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasAttachments {
        HasAttachments::registerMediaCollections as registerAttachmentCollection;
    }

    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    use RecordsActivity;

    public const string LOGO = 'logo';

    protected static function booted(): void
    {
        static::addGlobalScope(new AccessiblePropertyScope);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'status' => PropertyStatus::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->registerAttachmentCollection();
        $this->addMediaCollection(self::LOGO)->singleFile()->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml']);
    }

    public function isActive(): bool
    {
        return $this->status === PropertyStatus::Active;
    }
}
