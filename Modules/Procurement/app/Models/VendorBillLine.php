<?php

namespace Modules\Procurement\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Procurement\Database\Factories\VendorBillLineFactory;
use Modules\Procurement\Enums\ExpenseClass;

/**
 * A billed item (against an order line) or a described service line.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $vendor_bill_id
 * @property int|null $purchase_order_line_id
 * @property int|null $item_id
 * @property string|null $description
 * @property ExpenseClass|null $expense_class
 * @property string $quantity
 * @property string $unit_price
 * @property int|null $tax_category_id
 * @property string $net_amount
 * @property string $tax_amount
 * @property string $line_total
 * @property string $grni_amount
 * @property-read VendorBill $bill
 */
#[UseFactory(VendorBillLineFactory::class)]
#[Fillable(['property_id', 'vendor_bill_id', 'purchase_order_line_id', 'item_id', 'description', 'expense_class', 'quantity', 'unit_price', 'tax_category_id', 'net_amount', 'tax_amount', 'line_total', 'grni_amount'])]
class VendorBillLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<VendorBillLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expense_class' => ExpenseClass::class, 'quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2', 'grni_amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<VendorBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }
}
