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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Procurement\Database\Factories\PurchaseRequisitionFactory;
use Modules\Procurement\Enums\PurchaseRequisitionStatus;
use Modules\Procurement\Enums\RequisitionSource;

/**
 * A department's request to buy items (Step 5.5), approved through the approval engine; it can go to an RFQ or straight to a purchase order.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $requisition_no
 * @property int|null $department_id
 * @property Carbon|null $needed_on
 * @property PurchaseRequisitionStatus $status
 * @property RequisitionSource $source
 * @property string|null $notes
 * @property int $requested_by
 * @property int|null $approval_request_id
 * @property string $estimated_value
 * @property Carbon|null $approved_at
 * @property-read Collection<int, PurchaseRequisitionLine> $lines
 */
#[UseFactory(PurchaseRequisitionFactory::class)]
#[Fillable(['property_id', 'requisition_no', 'department_id', 'needed_on', 'status', 'source', 'notes', 'requested_by', 'approval_request_id', 'estimated_value', 'approved_at'])]
class PurchaseRequisition extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PurchaseRequisitionFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['needed_on' => 'date', 'status' => PurchaseRequisitionStatus::class, 'source' => RequisitionSource::class, 'estimated_value' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    /**
     * @return HasMany<PurchaseRequisitionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionLine::class)->orderBy('id');
    }
}
