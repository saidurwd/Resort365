<?php

namespace Modules\Reservation\Listeners;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Support\Facades\DB;
use Modules\Guest\Events\GuestsMerged;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;

/**
 * When two guest profiles are merged, their bookings move to the profile that was kept. A booking
 * that had both keeps one row for the kept guest (primary if either was). Guests are tenant-wide,
 * so this covers every property, not only those of the user who merged.
 */
class MoveReservationsToKeptGuest
{
    public function __construct(private readonly PropertyContext $properties) {}

    public function handle(GuestsMerged $event): void
    {
        $this->properties->unrestricted(fn () => DB::transaction(function () use ($event): void {
            Reservation::query()->where('primary_guest_id', $event->mergedGuestId)->update(['primary_guest_id' => $event->keptGuestId]);

            foreach (ReservationGuest::query()->where('guest_id', $event->mergedGuestId)->get() as $row) {
                $kept = ReservationGuest::query()->where('reservation_id', $row->reservation_id)->where('guest_id', $event->keptGuestId)->first();

                if ($kept instanceof ReservationGuest) {
                    $kept->forceFill(['is_primary' => $kept->is_primary || $row->is_primary])->save();
                    $row->delete();
                } else {
                    $row->forceFill(['guest_id' => $event->keptGuestId])->save();
                }
            }
        }));
    }
}
