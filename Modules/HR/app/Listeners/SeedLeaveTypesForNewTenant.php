<?php

namespace Modules\HR\Listeners;

use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Modules\HR\Actions\SeedLeaveTypes;

/**
 * A new tenant starts with the standard leave types and policies (Step 6.3).
 */
class SeedLeaveTypesForNewTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SeedLeaveTypes $seed,
    ) {}

    public function handle(TenantCreated $event): void
    {
        $this->context->run($event->tenant, fn (): int => $this->seed->handle());
    }
}
