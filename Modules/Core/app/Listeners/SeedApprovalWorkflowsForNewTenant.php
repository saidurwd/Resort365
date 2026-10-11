<?php

namespace Modules\Core\Listeners;

use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Modules\Core\Actions\SeedApprovalWorkflows;

/**
 * A new tenant starts with the default approval workflow of every registered document type (Step 5.1).
 */
class SeedApprovalWorkflowsForNewTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SeedApprovalWorkflows $seed,
    ) {}

    public function handle(TenantCreated $event): void
    {
        $this->context->run($event->tenant, fn (): int => $this->seed->handle());
    }
}
