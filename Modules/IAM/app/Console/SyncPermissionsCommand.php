<?php

namespace Modules\IAM\Console;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\IAM\Actions\SeedDefaultRoles;
use Modules\IAM\Actions\SyncPermissions;

#[Signature('permissions:sync {--prune : Delete permissions no module registers any more}')]
#[Description('Store registered permissions and update every tenant\'s default roles')]
class SyncPermissionsCommand extends Command
{
    public function handle(TenantContext $context, SyncPermissions $syncPermissions, SeedDefaultRoles $seedDefaultRoles): int
    {
        $result = $syncPermissions->handle((bool) $this->option('prune'));
        $this->components->info("Permissions: {$result['created']} created, {$result['deleted']} deleted.");

        $tenants = Tenant::query()->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            $context->run($tenant, function () use ($seedDefaultRoles): void {
                $seedDefaultRoles->handle();
            });
        }

        $this->components->info("Default roles updated for {$tenants->count()} tenant(s).");

        return self::SUCCESS;
    }
}
