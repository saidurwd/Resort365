<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Platform\Actions\SeedDefaultPlans;

#[Description('Add the Starter, Professional and Enterprise plans when they are missing')]
#[Signature('platform:seed-plans')]
class SeedPlansCommand extends Command
{
    public function handle(SeedDefaultPlans $seed): int
    {
        $this->info(trans_choice(':count plan added.|:count plans added.', $seed->handle()));

        return self::SUCCESS;
    }
}
