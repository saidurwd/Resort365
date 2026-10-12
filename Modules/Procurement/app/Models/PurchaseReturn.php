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
use Modules\Procurement\Database\Factories\PurchaseReturnFactory;

/**
 * Goods sent back to a vendor with a debit note (Step 5.6).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $return_no
 * @property string $debit_note_no
 * @property int $goods_receipt_id
 * @property int $vendor_id
 * @property int $store_id
 * @property Carbon $returned_on
 * @property string $reason
 * @property string|null $notes
 * @property string $total_value
 * @property string $tax_value
 * @property int $created_by
 * @property-read Collection<int, PurchaseReturnLine> $lines
 * @property-read Vendor $vendor
 * @property-read GoodsReceipt $receipt
 */
#[UseFactory(PurchaseReturnFactory::class)]
#[Fillable(['property_id', 'return_no', 'debit_note_no', 'goods_receipt_id', 'vendor_id', 'store_id', 'returned_on', 'reason', 'notes', 'total_value', 'tax_value', 'created_by'])]
class PurchaseReturn extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PurchaseReturnFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['returned_on' => 'date', 'total_value' => 'decimal:2', 'tax_value' => 'decimal:2'];
    }

    /**
     * @return HasMany<PurchaseReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }
}
