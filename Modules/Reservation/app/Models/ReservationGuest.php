<?php

namespace Modules\Reservation\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Reservation\Database\Factories\ReservationGuestFactory;

/**
 * A guest of a reservation (optionally of one item). The primary guest is the booker.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $reservation_id
 * @property int|null $reservation_item_id
 * @property int $guest_id
 * @property bool $is_primary
 */
#[UseFactory(ReservationGuestFactory::class)]
#[Fillable(['property_id', 'reservation_id', 'reservation_item_id', 'guest_id', 'is_primary'])]
class ReservationGuest extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ReservationGuestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
