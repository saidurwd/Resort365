<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Platform\Actions\RunBilling;

#[Description('End trials, renew subscriptions with their invoices, cancel and suspend tenants (daily)')]
#[Signature('platform:billing')]
class RunBillingCommand extends Command
{
    public function handle(RunBilling $billing): int
    {
        $counts = $billing->handle();
        $this->info(__('Trials ended: :started. Renewed: :renewed. Cancelled: :cancelled. Suspended for non-payment: :suspended. Failed: :failed.', $counts));

        return $counts['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
