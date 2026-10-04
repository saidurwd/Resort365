<?php

namespace Modules\FrontOffice\Actions;

use App\Support\Actions\Action;
use Modules\Billing\Contracts\FolioSettlement;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Reservation\Contracts\StayOperations;

/**
 * Posts the stay's room nights that are not on a folio yet (all of them until night audit posts
 * them nightly, Step 2.6), then marks them posted, in one transaction.
 */
class PostStayCharges extends Action
{
    public function __construct(
        private readonly StayOperations $stays,
        private readonly FolioSettlement $settlement,
    ) {}

    /**
     * @return int how many nights were posted
     *
     * @throws ChargeRejected
     */
    public function handle(int $reservationId, ?int $userId = null): int
    {
        return $this->transaction(function () use ($reservationId, $userId): int {
            $nights = $this->stays->unpostedNights($reservationId);
            $posted = $this->settlement->postRoomNights($reservationId, $nights, $userId);
            $this->stays->markNightsPosted($posted);

            return count($posted);
        }, attempts: 3);
    }
}
