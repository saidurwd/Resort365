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
use Modules\Procurement\Database\Factories\RfqFactory;
use Modules\Procurement\Enums\RfqStatus;

/**
 * A request for quotation: a purchase requisition sent to several vendors (Step 5.5).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $rfq_no
 * @property int $purchase_requisition_id
 * @property RfqStatus $status
 * @property Carbon|null $due_on
 * @property string|null $notes
 * @property int $created_by
 * @property Carbon|null $sent_at
 * @property-read PurchaseRequisition $requisition
 * @property-read Collection<int, RfqVendor> $vendors
 * @property-read Collection<int, VendorQuotation> $quotations
 */
#[UseFactory(RfqFactory::class)]
#[Fillable(['property_id', 'rfq_no', 'purchase_requisition_id', 'status', 'due_on', 'notes', 'created_by', 'sent_at'])]
class Rfq extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RfqFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => RfqStatus::class, 'due_on' => 'date', 'sent_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<PurchaseRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    /**
     * @return HasMany<RfqVendor, $this>
     */
    public function vendors(): HasMany
    {
        return $this->hasMany(RfqVendor::class)->orderBy('id');
    }

    /**
     * @return HasMany<VendorQuotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(VendorQuotation::class)->orderBy('id');
    }
}
