<?php

namespace Modules\Reservation\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Reservation\Database\Factories\InventoryLockFactory;
use Modules\Reservation\Enums\LockType;

/**
 * A room taken for one night (ARCHITECTURE §6.1). The unique (room_id, stay_date) index makes a
 * second lock on the same room-night fail, which is how double booking is prevented.
 * Locks are written in bulk by the booking flow (Step 1.6), so they are not audited one by one.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $room_id
 * @property Carbon $stay_date
 * @property LockType $lock_type
 * @property int|null $reservation_id
 * @property int|null $reservation_item_id
 * @property int|null $block_id
 * @property string|null $note
 */
#[UseFactory(InventoryLockFactory::class)]
#[Fillable(['property_id', 'room_id', 'stay_date', 'lock_type', 'reservation_id', 'reservation_item_id', 'block_id', 'note'])]
class InventoryLock extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<InventoryLockFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['stay_date' => 'date', 'lock_type' => LockType::class];
    }
}
