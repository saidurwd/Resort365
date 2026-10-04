<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\Folio;
use Modules\Billing\Services\FolioLedger;
use Modules\Reservation\Events\ReservationCreated;

/**
 * Every new booking gets its guest folio (ARCHITECTURE §5.9).
 */
class OpenGuestFolio
{
    public function __construct(private readonly FolioLedger $ledger) {}

    public function handle(ReservationCreated $event): void
    {
        DB::transaction(fn (): Folio => $this->ledger->guestFolio($event->reservationId), 3);
    }
}
