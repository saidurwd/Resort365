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
use Modules\Procurement\Database\Factories\PurchaseOrderFactory;
use Modules\Procurement\Enums\PurchaseOrderStatus;

/**
 * A purchase order to a vendor (Step 5.5): approved by amount, emailed as a PDF; receiving comes in Step 5.6.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $order_no
 * @property int $vendor_id
 * @property int|null $purchase_requisition_id
 * @property int|null $vendor_quotation_id
 * @property int $store_id
 * @property int|null $department_id
 * @property PurchaseOrderStatus $status
 * @property Carbon $ordered_on
 * @property Carbon|null $expected_on
 * @property int $payment_terms_days
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property string|null $notes
 * @property int $created_by
 * @property int|null $approval_request_id
 * @property Carbon|null $approved_at
 * @property Carbon|null $sent_at
 * @property string|null $sent_to
 * @property Carbon|null $cancelled_at
 * @property-read Vendor $vendor
 * @property-read Collection<int, PurchaseOrderLine> $lines
 */
#[UseFactory(PurchaseOrderFactory::class)]
#[Fillable(['property_id', 'order_no', 'vendor_id', 'purchase_requisition_id', 'vendor_quotation_id', 'store_id', 'department_id', 'status', 'ordered_on', 'expected_on', 'payment_terms_days', 'currency', 'subtotal', 'tax_total', 'total', 'notes', 'created_by', 'approval_request_id', 'approved_at', 'sent_at', 'sent_to', 'cancelled_at'])]
class PurchaseOrder extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => PurchaseOrderStatus::class, 'ordered_on' => 'date', 'expected_on' => 'date', 'payment_terms_days' => 'integer', 'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'approved_at' => 'datetime', 'sent_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('id');
    }
}
