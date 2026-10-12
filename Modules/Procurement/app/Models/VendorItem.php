<?php

namespace Modules\Procurement\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Procurement\Database\Factories\VendorItemFactory;

/**
 * A vendor's price for an item (the vendor price list).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $vendor_id
 * @property int $item_id
 * @property string|null $vendor_sku
 * @property string $price
 * @property string $min_quantity
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property-read Vendor $vendor
 */
#[UseFactory(VendorItemFactory::class)]
#[Fillable(['vendor_id', 'item_id', 'vendor_sku', 'price', 'min_quantity', 'valid_from', 'valid_to'])]
class VendorItem extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<VendorItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['price' => 'decimal:4', 'min_quantity' => 'decimal:4', 'valid_from' => 'date', 'valid_to' => 'date'];
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
