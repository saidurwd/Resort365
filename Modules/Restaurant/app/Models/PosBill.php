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
use Modules\Restaurant\Database\Factories\PosBillFactory;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\CompReason;

/**
 * A restaurant bill (ARCHITECTURE §5.10.7, §8.4): the whole order or one part of a split, numbered per
 * outlet without gaps, with its money frozen when printed: subtotal (lines before discounts), discounts,
 * service charge, other taxes (tax_breakdown: code, name, rate, amount) and the grand total due. When
 * prices include tax, grand_total = subtotal − discount_total and the taxes are inside it. Settled bills
 * never change; a manager voids them (same business date).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property int $pos_order_id
 * @property int $sequence
 * @property string $bill_no
 * @property Carbon $business_date
 * @property string|null $split_label
 * @property string $subtotal
 * @property string $discount_total
 * @property string $service_charge
 * @property string $tax_total
 * @property string $grand_total
 * @property string $tip_total
 * @property string $paid_total
 * @property list<array{code: string, name: string, rate: string, amount: string}>|null $tax_breakdown
 * @property BillStatus $status
 * @property bool $is_complimentary
 * @property CompReason|null $comp_reason
 * @property string|null $comp_note
 * @property int $print_count
 * @property int $receipt_count
 * @property Carbon|null $printed_at
 * @property Carbon|null $settled_at
 * @property int|null $settled_by
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property int|null $manager_approval_id
 * @property int|null $created_by
 */
#[UseFactory(PosBillFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'pos_order_id', 'sequence', 'bill_no', 'business_date', 'split_label', 'subtotal', 'discount_total', 'service_charge',
    'tax_total', 'grand_total', 'tip_total', 'paid_total', 'tax_breakdown', 'status', 'is_complimentary', 'comp_reason', 'comp_note', 'print_count',
    'receipt_count', 'printed_at', 'settled_at', 'settled_by', 'voided_at', 'voided_by', 'void_reason', 'manager_approval_id', 'created_by',
])]
class PosBill extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PosBillFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'tip_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'tax_breakdown' => 'array',
            'status' => BillStatus::class,
            'is_complimentary' => 'boolean',
            'comp_reason' => CompReason::class,
            'printed_at' => 'datetime',
            'settled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PosBillLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PosBillLine::class)->orderBy('id');
    }

    /**
     * @return HasMany<PosPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<PosOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
