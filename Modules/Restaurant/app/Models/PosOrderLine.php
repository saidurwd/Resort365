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
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\PosOrderLineFactory;
use Modules\Restaurant\Enums\Course;
use Modules\Restaurant\Enums\DiscountType;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Enums\VoidReason;

/**
 * A line of a POS order (ARCHITECTURE §8.4): the item (and variant) as named and priced when ordered, the
 * modifiers chosen (snapshot: group, name, price), course, seat, the station that makes it, notes.
 * Pending until sent; held lines wait for "fire"; a sent line is never deleted, only voided (reason,
 * who, a manager's approval when needed, wastage if already made).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $pos_order_id
 * @property int $menu_item_id
 * @property int|null $menu_item_variant_id
 * @property string $name_snapshot
 * @property string|null $variant_snapshot
 * @property int $quantity
 * @property string $unit_price
 * @property list<array{group: string, name: string, price: string}>|null $modifiers
 * @property string $modifier_total
 * @property string $line_total
 * @property Course $course
 * @property int|null $seat_no
 * @property int|null $kitchen_station_id
 * @property OrderLineStatus $status
 * @property bool $is_held
 * @property Carbon|null $sent_at
 * @property string|null $notes
 * @property VoidReason|null $void_reason
 * @property string|null $void_note
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property int|null $manager_approval_id
 * @property bool $is_wastage
 * @property int|null $added_by
 * @property DiscountType|null $discount_type
 * @property string|null $discount_value percent or amount
 * @property string|null $discount_reason
 * @property int|null $discount_by
 * @property int|null $discount_approval_id
 */
#[UseFactory(PosOrderLineFactory::class)]
#[Fillable([
    'property_id', 'pos_order_id', 'menu_item_id', 'menu_item_variant_id', 'name_snapshot', 'variant_snapshot', 'quantity', 'unit_price',
    'modifiers', 'modifier_total', 'line_total', 'course', 'seat_no', 'kitchen_station_id', 'status', 'is_held', 'sent_at', 'notes', 'void_reason',
    'void_note', 'voided_by', 'voided_at', 'manager_approval_id', 'is_wastage', 'added_by',
])]
class PosOrderLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PosOrderLineFactory> */
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
            'modifiers' => 'array',
            'unit_price' => 'decimal:2',
            'modifier_total' => 'decimal:2',
            'line_total' => 'decimal:2',
            'course' => Course::class,
            'status' => OrderLineStatus::class,
            'is_held' => 'boolean',
            'sent_at' => 'datetime',
            'void_reason' => VoidReason::class,
            'voided_at' => 'datetime',
            'is_wastage' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PosOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }
}
