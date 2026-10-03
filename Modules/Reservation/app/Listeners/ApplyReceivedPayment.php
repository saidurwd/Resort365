<?php

namespace Modules\Reservation\Listeners;

use Modules\Billing\Events\PaymentReceived;
use Modules\Reservation\Actions\ApplyPayment;

/**
 * Billing received money for a reservation (ARCHITECTURE §4.5): update its payment status and
 * confirm it once the deposit is covered. Runs straight away, so the page shows the new status.
 */
class ApplyReceivedPayment
{
    public function __construct(private readonly ApplyPayment $apply) {}

    public function handle(PaymentReceived $event): void
    {
        if ($event->reservationId === null || $event->reservationPaidTotal === null) {
            return;
        }

        $this->apply->handle($event->reservationId, $event->reservationPaidTotal, $event->amount, $event->receiptNo, $event->receivedBy);
    }
}
