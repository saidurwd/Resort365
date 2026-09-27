<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionRegistry;
use Modules\IAM\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the default (system) roles for the current tenant and sets their permissions
 * from the registry. Custom roles are never touched. Run inside TenantContext::run().
 */
class SeedDefaultRoles extends Action
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly PermissionRegistrar $registrar,
    ) {}

    public function handle(): void
    {
        $this->transaction(function (): void {
            foreach (DefaultRole::cases() as $default) {
                $role = Role::query()->firstOrNew(['name' => $default->value, 'guard_name' => 'web']);
                $role->description = $default->description();
                $role->forceFill(['is_system' => true])->save();

                $role->syncPermissions($this->registry->namesFor($default));
            }
        });

        $this->registrar->forgetCachedPermissions();
    }
}
