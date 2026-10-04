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
use Modules\Housekeeping\Database\Factories\RoomBlockFactory;
use Modules\Housekeeping\Enums\BlockStatus;
use Modules\Housekeeping\Enums\BlockType;

/**
 * A room out of order or out of service for nights [from_date, to_date) (ARCHITECTURE §5.11). An
 * out-of-order block locks the nights in inventory (Reservation's RoomBlocks, block_id = this id)
 * so the room cannot be sold; ending it releases the remaining nights.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $room_id
 * @property BlockType $type
 * @property Carbon $from_date
 * @property Carbon $to_date
 * @property string $reason
 * @property BlockStatus $status
 * @property int|null $created_by
 * @property int|null $ended_by
 * @property Carbon|null $ended_at
 */
#[UseFactory(RoomBlockFactory::class)]
#[Fillable([
    'property_id', 'room_id', 'type', 'from_date', 'to_date', 'reason', 'status', 'created_by', 'ended_by', 'ended_at',
])]
class RoomBlock extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RoomBlockFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'status' => BlockStatus::class,
            'from_date' => 'date',
            'to_date' => 'date',
            'ended_at' => 'datetime',
        ];
    }
}
