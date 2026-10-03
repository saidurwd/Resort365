<?php

namespace Modules\Reservation\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationLog;

/**
 * Writes a reservation's history (reservation_logs). The user is the signed-in staff member, or
 * none for the system (hold expiry, payments arriving from elsewhere).
 */
class ReservationLogger
{
    /**
     * @param  array<string, mixed>  $changes  e.g. ['check_in' => ['2026-11-10', '2026-11-12']]
     */
    public function log(Reservation $reservation, ReservationLogAction $action, string $description, array $changes = [], ?int $userId = null): ReservationLog
    {
        $id = $userId ?? Auth::guard('web')->id();

        return ReservationLog::query()->create([
            'property_id' => $reservation->property_id,
            'reservation_id' => $reservation->id,
            'action' => $action,
            'description' => $description,
            'changes' => $changes === [] ? null : $changes,
            'user_id' => is_numeric($id) ? (int) $id : null,
        ]);
    }
}
