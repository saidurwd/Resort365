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
use Modules\Housekeeping\Database\Factories\LostFoundItemFactory;
use Modules\Housekeeping\Enums\LostItemStatus;

/**
 * The lost & found register (ARCHITECTURE §5.11): what was found, when and where, where it is
 * kept, and whether it was returned (to a guest profile or a named person) or disposed of.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property Carbon $found_on
 * @property int|null $room_id
 * @property string $found_at
 * @property string $description
 * @property int|null $found_by
 * @property string|null $stored_at
 * @property LostItemStatus $status
 * @property int|null $guest_id
 * @property string|null $claimed_by_name
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $notes
 */
#[UseFactory(LostFoundItemFactory::class)]
#[Fillable([
    'property_id', 'found_on', 'room_id', 'found_at', 'description', 'found_by', 'stored_at', 'status', 'guest_id', 'claimed_by_name', 'closed_at',
    'closed_by', 'notes',
])]
class LostFoundItem extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<LostFoundItemFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'found_on' => 'date',
            'status' => LostItemStatus::class,
            'closed_at' => 'datetime',
        ];
    }
}
