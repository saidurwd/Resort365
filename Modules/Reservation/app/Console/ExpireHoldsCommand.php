<?php

namespace Modules\Reservation\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Reservation\Jobs\ExpireTentativeHolds;

#[Signature('reservation:expire-holds')]
#[Description('Cancel tentative bookings whose deposit was not paid in time (also scheduled every five minutes)')]
class ExpireHoldsCommand extends Command
{
    public function handle(): int
    {
        $cancelled = (int) ExpireTentativeHolds::dispatchSync();
        $this->components->info("Expired holds cancelled: {$cancelled}.");

        return self::SUCCESS;
    }
}
