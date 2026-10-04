<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\OpenFolio;
use Modules\Billing\Actions\SaveRoutingRule;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Services\FolioLedger;
use Modules\Reservation\Events\ReservationCreated;

/**
 * Every new booking gets its guest folio (ARCHITECTURE §5.9). A group booking also gets a master
 * folio, and its room charges are routed there (extras stay on the guest folio unless routed).
 */
class OpenGuestFolio
{
    public function __construct(
        private readonly FolioLedger $ledger,
        private readonly OpenFolio $open,
        private readonly SaveRoutingRule $route,
    ) {}

    public function handle(ReservationCreated $event): void
    {
        DB::transaction(function () use ($event): void {
            $this->ledger->guestFolio($event->reservationId);

            if ($this->ledger->reservation($event->reservationId)->groupName !== null) {
                $master = $this->open->handle($event->reservationId, FolioType::Master, BillTo::Guest);
                $this->route->handle($event->reservationId, ChargeCategory::Room, $master);
            }
        }, 3);
    }
}
