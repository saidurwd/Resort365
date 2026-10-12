<?php

namespace Modules\Procurement\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Procurement\Database\Factories\VendorQuotationFactory;
use Modules\Procurement\Enums\QuotationStatus;

/**
 * A vendor's answer to an RFQ.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $rfq_id
 * @property int $vendor_id
 * @property string|null $reference
 * @property Carbon $quoted_on
 * @property Carbon|null $valid_until
 * @property string $total
 * @property QuotationStatus $status
 * @property string|null $notes
 * @property-read Vendor $vendor
 * @property-read Rfq $rfq
 * @property-read Collection<int, VendorQuotationLine> $lines
 */
#[UseFactory(VendorQuotationFactory::class)]
#[Fillable(['property_id', 'rfq_id', 'vendor_id', 'reference', 'quoted_on', 'valid_until', 'total', 'status', 'notes'])]
class VendorQuotation extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<VendorQuotationFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quoted_on' => 'date', 'valid_until' => 'date', 'total' => 'decimal:2', 'status' => QuotationStatus::class];
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

    /**
     * @return HasMany<VendorQuotationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(VendorQuotationLine::class)->orderBy('id');
    }
}
