<?php

namespace Modules\FrontOffice\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Modules\FrontOffice\Events\GuestCheckedIn;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Exceptions\StayNotPossible;

/**
 * Checks a booking in from the front desk (ARCHITECTURE §5.7): the Reservation module marks all of
 * its rooms (or one room of a group) checked in — confirmed bookings arriving today or earlier —
 * then GuestCheckedIn tells the other modules which rooms are now occupied.
 */
class CheckInGuest extends Action
{
    public function __construct(
        private readonly StayOperations $stays,
        private readonly ReservationLookup $reservations,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  int|null  $itemId  one room or cottage of the booking; null = all not checked in yet
     *
     * @throws StayNotPossible
     */
    public function handle(int $reservationId, ?int $userId = null, ?int $itemId = null): ReservationSummary
    {
        return $this->transaction(function () use ($reservationId, $userId, $itemId): ReservationSummary {
            $items = $this->stays->checkIn($reservationId, $userId, $itemId);
            $reservation = $this->reservations->find($reservationId) ?? throw new StayNotPossible(__('Unknown reservation.'));

            GuestCheckedIn::dispatch($this->tenants->tenantOrFail(self::class)->id, $reservation->propertyId, $reservation->id, $this->stays->roomIds($reservation->id, $items));

            return $reservation;
        });
    }
}
