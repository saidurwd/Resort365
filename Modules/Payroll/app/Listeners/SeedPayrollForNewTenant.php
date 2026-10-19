<?php

namespace Modules\Payroll\Listeners;

use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Modules\Payroll\Actions\SeedBangladeshPayroll;

/**
 * A new tenant starts with the Bangladesh payroll starter pack (Step 6.4).
 */
class SeedPayrollForNewTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SeedBangladeshPayroll $seed,
    ) {}

    public function handle(TenantCreated $event): void
    {
        $this->context->run($event->tenant, fn (): array => $this->seed->handle());
    }
}
