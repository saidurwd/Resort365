<?php

namespace Modules\FrontOffice\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Modules\FrontOffice\Events\GuestCheckedIn;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Exceptions\StayNotPossible;

/**
 * Checks a booking in from the front desk (ARCHITECTURE §5.7): the Reservation module marks it
 * Checked in (confirmed bookings arriving today or earlier), then GuestCheckedIn tells the other
 * modules which rooms are now occupied.
 */
class CheckInGuest extends Action
{
    public function __construct(
        private readonly StayOperations $stays,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @throws StayNotPossible
     */
    public function handle(int $reservationId, ?int $userId = null): ReservationSummary
    {
        return $this->transaction(function () use ($reservationId, $userId): ReservationSummary {
            $reservation = $this->stays->checkIn($reservationId, $userId);

            GuestCheckedIn::dispatch($this->tenants->tenantOrFail(self::class)->id, $reservation->propertyId, $reservation->id, $this->stays->roomIds($reservation->id));

            return $reservation;
        });
    }
}
