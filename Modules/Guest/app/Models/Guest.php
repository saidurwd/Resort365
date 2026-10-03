<?php

namespace Modules\Guest\Models;

use App\Support\Attachments\HasAttachments;
use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Guest\Database\Factories\GuestFactory;
use Modules\Guest\Enums\IdType;
use Modules\Guest\Enums\VipLevel;
use Spatie\MediaLibrary\HasMedia;

/**
 * A guest profile (ARCHITECTURE §5.8), shared by all the tenant's properties. ID documents are
 * attachments. The ID number is encrypted and hidden, so it never reaches the audit log or JSON;
 * id_number_hash (SaveGuest) finds duplicates. Phone numbers are stored in international format.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $title
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $nationality_code
 * @property Carbon|null $date_of_birth
 * @property IdType|null $id_type
 * @property string|null $id_number
 * @property string|null $id_number_hash
 * @property Carbon|null $id_expiry
 * @property array<string, string|null>|null $address
 * @property int|null $company_id
 * @property VipLevel $vip_level
 * @property bool $is_blacklisted
 * @property string|null $blacklist_reason
 * @property Carbon|null $blacklisted_at
 * @property int|null $blacklisted_by
 * @property list<string>|null $preferences
 * @property bool $marketing_consent
 * @property string|null $notes
 * @property int|null $merged_into_id
 * @property-read string $full_name
 * @property-read Company|null $company
 */
#[UseFactory(GuestFactory::class)]
#[Fillable([
    'title', 'first_name', 'last_name', 'email', 'phone', 'nationality_code', 'date_of_birth', 'id_type', 'id_number',
    'id_expiry', 'address', 'company_id', 'vip_level', 'preferences', 'marketing_consent', 'notes',
])]
#[Hidden(['id_number', 'id_number_hash'])]
class Guest extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasAttachments;

    /** @use HasFactory<GuestFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'vip_level' => 'none',
        'is_blacklisted' => false,
        'marketing_consent' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'id_type' => IdType::class,
            'id_number' => 'encrypted',
            'id_expiry' => 'date',
            'address' => 'array',
            'vip_level' => VipLevel::class,
            'is_blacklisted' => 'boolean',
            'blacklisted_at' => 'datetime',
            'preferences' => 'array',
            'marketing_consent' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([$this->title, $this->first_name, $this->last_name]))));
    }

    /**
     * The ID number with all but the last four characters masked.
     */
    public function maskedIdNumber(): ?string
    {
        $number = $this->id_number;

        if ($number === null || $number === '') {
            return null;
        }

        return str_repeat('•', max(0, mb_strlen($number) - 4)).mb_substr($number, -4);
    }
}
