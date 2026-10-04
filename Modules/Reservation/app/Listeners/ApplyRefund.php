<?php

namespace Modules\Reservation\Listeners;

use Modules\Billing\Events\RefundIssued;
use Modules\Reservation\Actions\ApplyRefund as ApplyRefundAction;

/**
 * Billing paid money back (ARCHITECTURE §4.5): the booking's paid total and payment status follow.
 */
class ApplyRefund
{
    public function __construct(private readonly ApplyRefundAction $apply) {}

    public function handle(RefundIssued $event): void
    {
        if ($event->reservationId !== null && $event->reservationPaidTotal !== null) {
            $this->apply->handle($event->reservationId, $event->reservationPaidTotal, $event->amount);
        }
    }
}
