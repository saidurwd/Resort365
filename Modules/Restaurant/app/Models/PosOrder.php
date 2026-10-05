<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\PosOrderFactory;
use Modules\Restaurant\Enums\DiscountType;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\OrderType;

/**
 * An order at a table or for takeaway (ARCHITECTURE §5.10.4–5.10.5): numbered per outlet and business date,
 * taken by a waiter, with its lines and the kitchen tickets it sent. subtotal is the lines before tax
 * (bills add discounts and tax, Step 3.6). A merged order points at the order that took its lines.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property int|null $pos_session_id
 * @property string $order_no
 * @property Carbon $business_date
 * @property OrderType $order_type
 * @property int|null $dining_table_id
 * @property int $covers
 * @property int $waiter_id
 * @property OrderStatus $status
 * @property string $subtotal
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property int|null $merged_into_id
 * @property string|null $notes
 * @property DiscountType|null $discount_type
 * @property string|null $discount_value percent or amount
 * @property string|null $discount_reason
 * @property int|null $discount_by
 * @property int|null $discount_approval_id
 */
#[UseFactory(PosOrderFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'pos_session_id', 'order_no', 'business_date', 'order_type', 'dining_table_id', 'covers', 'waiter_id', 'status',
    'subtotal', 'opened_at', 'closed_at', 'merged_into_id', 'notes',
])]
class PosOrder extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PosOrderFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'business_date' => 'date',
            'order_type' => OrderType::class,
            'status' => OrderStatus::class,
            'subtotal' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PosOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PosOrderLine::class)->orderBy('id');
    }

    /**
     * @return HasMany<Kot, $this>
     */
    public function kots(): HasMany
    {
        return $this->hasMany(Kot::class)->orderBy('kot_no');
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return HasMany<PosBill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(PosBill::class)->orderBy('sequence');
    }

    public function isOpen(): bool
    {
        return $this->status === OrderStatus::Open;
    }
}
