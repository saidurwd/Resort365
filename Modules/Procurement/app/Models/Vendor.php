<?php

namespace Modules\Procurement\Models;

use App\Support\Attachments\HasAttachments;
use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Procurement\Database\Factories\VendorFactory;
use Modules\Procurement\Enums\VendorStatus;
use Spatie\MediaLibrary\HasMedia;

/**
 * A supplier shared by the tenant's properties (Step 5.5): profile, bank details, payment terms, rating; contacts and a price list hang off it.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $tax_id
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string $currency
 * @property int $payment_terms_days
 * @property int|null $rating
 * @property VendorStatus $status
 * @property string|null $bank_name
 * @property string|null $bank_account_name
 * @property string|null $bank_account_no
 * @property string|null $bank_branch
 * @property string|null $notes
 * @property-read Collection<int, VendorContact> $contacts
 * @property-read Collection<int, VendorItem> $prices
 */
#[UseFactory(VendorFactory::class)]
#[Fillable(['code', 'name', 'tax_id', 'email', 'phone', 'address', 'currency', 'payment_terms_days', 'rating', 'status', 'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch', 'notes'])]
class Vendor extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasAttachments;

    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payment_terms_days' => 'integer', 'rating' => 'integer', 'status' => VendorStatus::class];
    }

    /**
     * @return HasMany<VendorContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(VendorContact::class)->orderBy('id');
    }

    /**
     * @return HasMany<VendorItem, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(VendorItem::class, 'vendor_id')->orderBy('id');
    }
}
