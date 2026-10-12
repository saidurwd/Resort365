<?php

namespace Modules\Procurement\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Procurement\Database\Factories\VendorContactFactory;

/**
 * A person at a vendor.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $vendor_id
 * @property string $name
 * @property string|null $role
 * @property string|null $email
 * @property string|null $phone
 * @property bool $is_primary
 * @property-read Vendor $vendor
 */
#[UseFactory(VendorContactFactory::class)]
#[Fillable(['vendor_id', 'name', 'role', 'email', 'phone', 'is_primary'])]
class VendorContact extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<VendorContactFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
