<?php

namespace Modules\Reservation\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Reservation\Database\Factories\ReservationLogFactory;
use Modules\Reservation\Enums\ReservationLogAction;

/**
 * One entry of a reservation's history (ARCHITECTURE §6.7), written by ReservationLogger.
 * Entries are never changed; the log itself is the audit, so it is not audited again.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $reservation_id
 * @property ReservationLogAction $action
 * @property string $description
 * @property array<string, mixed>|null $changes
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
#[UseFactory(ReservationLogFactory::class)]
#[Fillable(['property_id', 'reservation_id', 'action', 'description', 'changes', 'user_id'])]
class ReservationLog extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ReservationLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['action' => ReservationLogAction::class, 'changes' => 'array'];
    }
}
