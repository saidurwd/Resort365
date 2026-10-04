<?php

namespace Modules\Housekeeping\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Housekeeping\Jobs\RunPreventiveMaintenance;

#[Signature('housekeeping:preventive')]
#[Description('Open the work orders of preventive maintenance schedules that are due (also scheduled daily)')]
class PreventiveMaintenanceCommand extends Command
{
    public function handle(): int
    {
        $opened = (int) RunPreventiveMaintenance::dispatchSync();
        $this->components->info("Preventive work orders opened: {$opened}.");

        return self::SUCCESS;
    }
}
