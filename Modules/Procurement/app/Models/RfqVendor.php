<?php

namespace Modules\Procurement\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Procurement\Database\Factories\RfqVendorFactory;

/**
 * A vendor an RFQ was sent to.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $rfq_id
 * @property int $vendor_id
 * @property Carbon|null $sent_at
 * @property-read Vendor $vendor
 * @property-read Rfq $rfq
 */
#[UseFactory(RfqVendorFactory::class)]
#[Fillable(['property_id', 'rfq_id', 'vendor_id', 'sent_at'])]
class RfqVendor extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RfqVendorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }
}
