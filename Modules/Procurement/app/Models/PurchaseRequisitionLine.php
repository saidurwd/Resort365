<?php

namespace Modules\Procurement\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Procurement\Database\Factories\PurchaseRequisitionLineFactory;

/**
 * An item and quantity on a purchase requisition.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $purchase_requisition_id
 * @property int $item_id
 * @property string $quantity
 * @property string $estimated_unit_cost
 * @property string|null $notes
 * @property-read PurchaseRequisition $requisition
 */
#[UseFactory(PurchaseRequisitionLineFactory::class)]
#[Fillable(['property_id', 'purchase_requisition_id', 'item_id', 'quantity', 'estimated_unit_cost', 'notes'])]
class PurchaseRequisitionLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PurchaseRequisitionLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'estimated_unit_cost' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<PurchaseRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }
}
