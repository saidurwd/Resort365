<?php

namespace Modules\Procurement\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Procurement\Database\Factories\VendorPaymentAllocationFactory;

/**
 * The part of a payment applied to one bill.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $vendor_payment_id
 * @property int $vendor_bill_id
 * @property string $amount
 * @property-read VendorPayment $payment
 * @property-read VendorBill $bill
 */
#[UseFactory(VendorPaymentAllocationFactory::class)]
#[Fillable(['property_id', 'vendor_payment_id', 'vendor_bill_id', 'amount'])]
class VendorPaymentAllocation extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<VendorPaymentAllocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<VendorPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(VendorPayment::class, 'vendor_payment_id');
    }

    /**
     * @return BelongsTo<VendorBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }
}
