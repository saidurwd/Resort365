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
use Modules\Procurement\Database\Factories\VendorBillFactory;
use Modules\Procurement\Enums\VendorBillKind;
use Modules\Procurement\Enums\VendorBillStatus;

/**
 * A vendor's invoice (Step 5.6): goods against a purchase order, matched three ways, or a service or expense bill. Approved bills are payable.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $bill_no
 * @property string|null $vendor_reference
 * @property int $vendor_id
 * @property int|null $purchase_order_id
 * @property VendorBillKind $kind
 * @property VendorBillStatus $status
 * @property Carbon $bill_date
 * @property Carbon $due_on
 * @property string $currency
 * @property int|null $department_id
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property string $paid_amount
 * @property bool $match_overridden
 * @property string|null $match_notes
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $approval_request_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $cancelled_at
 * @property-read Collection<int, VendorBillLine> $lines
 * @property-read Vendor $vendor
 * @property-read PurchaseOrder $order
 */
#[UseFactory(VendorBillFactory::class)]
#[Fillable(['property_id', 'bill_no', 'vendor_reference', 'vendor_id', 'purchase_order_id', 'kind', 'status', 'bill_date', 'due_on', 'currency', 'department_id', 'subtotal', 'tax_total', 'total', 'paid_amount', 'match_overridden', 'match_notes', 'notes', 'created_by', 'approval_request_id', 'approved_at', 'cancelled_at'])]
class VendorBill extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<VendorBillFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => VendorBillKind::class, 'status' => VendorBillStatus::class, 'bill_date' => 'date', 'due_on' => 'date', 'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'match_overridden' => 'boolean', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /**
     * @return HasMany<VendorBillLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(VendorBillLine::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
