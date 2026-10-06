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
use Modules\Restaurant\Database\Factories\TableReservationFactory;
use Modules\Restaurant\Enums\TableReservationStatus;

/**
 * A table booked at an outlet for a time and party size (ARCHITECTURE §5.10.12, §8.4): for an in-house
 * guest (reservation_id) or an outside customer (name and phone), with an occasion and notes. reserved_for
 * is stored in UTC and shown in the property's time. The table shows as reserved on the POS floor until
 * the party is seated (which opens its order).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property int|null $dining_table_id
 * @property int|null $reservation_id
 * @property string $customer_name
 * @property string|null $phone
 * @property Carbon $reserved_for
 * @property int $duration_minutes
 * @property int $party_size
 * @property string|null $occasion
 * @property string|null $notes
 * @property TableReservationStatus $status
 * @property int|null $pos_order_id
 * @property int|null $created_by
 */
#[UseFactory(TableReservationFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'dining_table_id', 'reservation_id', 'customer_name', 'phone', 'reserved_for', 'duration_minutes', 'party_size', 'occasion',
    'notes', 'status', 'pos_order_id', 'created_by',
])]
class TableReservation extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<TableReservationFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reserved_for' => 'datetime',
            'status' => TableReservationStatus::class,
        ];
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
     * @return BelongsTo<PosOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }
}
