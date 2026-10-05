<?php

namespace Modules\Restaurant\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Restaurant\Database\Factories\PosBillLineFactory;

/**
 * An order line's share on a bill (ARCHITECTURE §8.4), frozen when the bill is printed: the quantity
 * share (a fraction when split equally or by amount), its amount before discounts, discount, taxes and
 * what it comes to. Written once with the bill, so not activity-logged.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $pos_bill_id
 * @property int $pos_order_line_id
 * @property string $name_snapshot
 * @property string $quantity
 * @property string $amount
 * @property string $discount
 * @property string $tax
 * @property string $gross
 */
#[UseFactory(PosBillLineFactory::class)]
#[Fillable(['property_id', 'pos_bill_id', 'pos_order_line_id', 'name_snapshot', 'quantity', 'amount', 'discount', 'tax', 'gross'])]
class PosBillLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PosBillLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'gross' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PosBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(PosBill::class, 'pos_bill_id');
    }

    /**
     * @return BelongsTo<PosOrderLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(PosOrderLine::class, 'pos_order_line_id');
    }
}
