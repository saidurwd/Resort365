<?php

namespace Modules\IAM\Listeners;

use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Modules\IAM\Actions\SeedDefaultRoles;
use Modules\IAM\Actions\SyncPermissions;

/**
 * Every new tenant starts with the default roles (ARCHITECTURE §3.2).
 */
class SeedRolesForNewTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly SyncPermissions $syncPermissions,
        private readonly SeedDefaultRoles $seedDefaultRoles,
    ) {}

    public function handle(TenantCreated $event): void
    {
        $this->syncPermissions->handle();

        $this->context->run($event->tenant, function (): void {
            $this->seedDefaultRoles->handle();
        });
    }
}
