<?php

namespace Modules\Inventory\Listeners;

use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Modules\Inventory\Actions\SeedStandardUnits;

/**
 * A new tenant starts with the standard units of measure (Step 5.2).
 */
class SeedUnitsForNewTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SeedStandardUnits $seed,
    ) {}

    public function handle(TenantCreated $event): void
    {
        $this->context->run($event->tenant, fn (): int => $this->seed->handle());
    }
}
