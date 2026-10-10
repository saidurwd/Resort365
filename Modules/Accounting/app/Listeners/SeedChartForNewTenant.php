<?php

namespace Modules\Accounting\Listeners;

use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Modules\Accounting\Actions\SeedChartOfAccounts;

/**
 * A new tenant starts with the USALI-aligned chart of accounts (ARCHITECTURE §5.14).
 */
class SeedChartForNewTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SeedChartOfAccounts $seed,
    ) {}

    public function handle(TenantCreated $event): void
    {
        $this->context->run($event->tenant, fn (): int => $this->seed->handle());
    }
}
