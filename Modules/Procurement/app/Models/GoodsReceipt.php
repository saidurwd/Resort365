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
use Modules\Procurement\Database\Factories\GoodsReceiptFactory;

/**
 * Goods received against a purchase order (Step 5.6): accepted quantities went into stock at the order price, rejected ones did not.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $receipt_no
 * @property int $purchase_order_id
 * @property int $vendor_id
 * @property int $store_id
 * @property Carbon $received_on
 * @property string|null $delivery_note
 * @property string|null $notes
 * @property int $received_by
 * @property string $total_value
 * @property-read Collection<int, GoodsReceiptLine> $lines
 * @property-read PurchaseOrder $order
 * @property-read Vendor $vendor
 */
#[UseFactory(GoodsReceiptFactory::class)]
#[Fillable(['property_id', 'receipt_no', 'purchase_order_id', 'vendor_id', 'store_id', 'received_on', 'delivery_note', 'notes', 'received_by', 'total_value'])]
class GoodsReceipt extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<GoodsReceiptFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['received_on' => 'date', 'total_value' => 'decimal:2'];
    }

    /**
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
