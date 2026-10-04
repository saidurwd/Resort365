<?php

namespace Modules\Housekeeping\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Housekeeping\Database\Factories\HousekeepingTaskFactory;
use Modules\Housekeeping\Enums\TaskStatus;
use Modules\Housekeeping\Enums\TaskType;

/**
 * A cleaning task for a room on a business date (ARCHITECTURE §5.11): created at check-out (departure)
 * and daily for rooms in house (stayover), assigned to an attendant, started, finished (the room is
 * clean) and inspected. Unique per room, date and type.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $room_id
 * @property Carbon $business_date
 * @property TaskType $type
 * @property TaskStatus $status
 * @property int|null $assigned_to
 * @property int|null $reservation_id
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $inspected_at
 * @property int|null $inspected_by
 * @property string|null $notes
 */
#[UseFactory(HousekeepingTaskFactory::class)]
#[Fillable([
    'property_id', 'room_id', 'business_date', 'type', 'status', 'assigned_to', 'reservation_id', 'started_at', 'finished_at', 'inspected_at',
    'inspected_by', 'notes',
])]
class HousekeepingTask extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<HousekeepingTaskFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'type' => TaskType::class,
            'status' => TaskStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'inspected_at' => 'datetime',
        ];
    }
}
