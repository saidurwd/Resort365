<?php

namespace Modules\Inventory\Models;

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
use Modules\Inventory\Database\Factories\StockRequisitionFactory;
use Modules\Inventory\Enums\RequisitionStatus;

/**
 * A department's request for items from a store (Step 5.4): approved through the approval engine, then issued by the store keeper.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $requisition_no
 * @property int $store_id
 * @property int $department_id
 * @property Carbon|null $needed_on
 * @property RequisitionStatus $status
 * @property string|null $notes
 * @property int $requested_by
 * @property int|null $approval_request_id
 * @property string $estimated_value
 * @property Carbon|null $approved_at
 * @property Carbon|null $issued_at
 * @property int|null $issue_id
 * @property-read Store $store
 * @property-read Collection<int, StockRequisitionLine> $lines
 */
#[UseFactory(StockRequisitionFactory::class)]
#[Fillable(['property_id', 'requisition_no', 'store_id', 'department_id', 'needed_on', 'status', 'notes', 'requested_by', 'approval_request_id', 'estimated_value', 'approved_at', 'issued_at', 'issue_id'])]
class StockRequisition extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StockRequisitionFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['needed_on' => 'date', 'status' => RequisitionStatus::class, 'estimated_value' => 'decimal:2', 'approved_at' => 'datetime', 'issued_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<StockRequisitionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockRequisitionLine::class)->orderBy('id');
    }
}
