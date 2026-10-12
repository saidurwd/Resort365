<?php

namespace Modules\Procurement\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Procurement\Database\Factories\VendorQuotationLineFactory;

/**
 * A quoted item, quantity and price.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $vendor_quotation_id
 * @property int $item_id
 * @property string $quantity
 * @property string $unit_price
 * @property int|null $lead_days
 * @property-read VendorQuotation $quotation
 */
#[UseFactory(VendorQuotationLineFactory::class)]
#[Fillable(['property_id', 'vendor_quotation_id', 'item_id', 'quantity', 'unit_price', 'lead_days'])]
class VendorQuotationLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<VendorQuotationLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'lead_days' => 'integer'];
    }

    /**
     * @return BelongsTo<VendorQuotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(VendorQuotation::class, 'vendor_quotation_id');
    }
}
