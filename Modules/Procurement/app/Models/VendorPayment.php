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
use Modules\Procurement\Database\Factories\VendorPaymentFactory;
use Modules\Procurement\Enums\VendorPaymentMethod;

/**
 * Money paid to a vendor (Step 5.6), allocated to bills; what is not allocated is an advance.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $payment_no
 * @property int $vendor_id
 * @property VendorPaymentMethod $method
 * @property Carbon $paid_on
 * @property string $amount
 * @property string $allocated_amount
 * @property string|null $reference
 * @property string|null $notes
 * @property int $created_by
 * @property-read Collection<int, VendorPaymentAllocation> $allocations
 * @property-read Vendor $vendor
 */
#[UseFactory(VendorPaymentFactory::class)]
#[Fillable(['property_id', 'payment_no', 'vendor_id', 'method', 'paid_on', 'amount', 'allocated_amount', 'reference', 'notes', 'created_by'])]
class VendorPayment extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<VendorPaymentFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['method' => VendorPaymentMethod::class, 'paid_on' => 'date', 'amount' => 'decimal:2', 'allocated_amount' => 'decimal:2'];
    }

    /**
     * @return HasMany<VendorPaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
