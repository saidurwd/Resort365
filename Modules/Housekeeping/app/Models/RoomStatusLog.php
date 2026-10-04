<?php

namespace Modules\Housekeeping\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Housekeeping\Database\Factories\RoomStatusLogFactory;
use Modules\Property\Enums\HousekeepingStatus;

/**
 * A change of a room's cleaning status (ARCHITECTURE §9.3 room_status_logs), written by RoomStatusChanger;
 * never changed afterwards, so it is not activity-logged itself.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $room_id
 * @property HousekeepingStatus $from_status
 * @property HousekeepingStatus $to_status
 * @property string $reason
 * @property int|null $changed_by
 */
#[UseFactory(RoomStatusLogFactory::class)]
#[Fillable([
    'property_id', 'room_id', 'from_status', 'to_status', 'reason', 'changed_by',
])]
class RoomStatusLog extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RoomStatusLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => HousekeepingStatus::class,
            'to_status' => HousekeepingStatus::class,
        ];
    }
}
