<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use App\Support\Authorization\PermissionRegistry;
use Modules\IAM\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Stores every registered permission (global table). With $prune, permissions no module
 * registers any more are deleted. Returns [created, deleted] counts.
 */
class SyncPermissions extends Action
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * @return array{created: int, deleted: int}
     */
    public function handle(bool $prune = false): array
    {
        return $this->transaction(function () use ($prune): array {
            $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
            $wanted = $this->registry->names();

            $missing = array_values(array_diff($wanted, $existing));

            foreach ($missing as $name) {
                Permission::query()->create(['name' => $name, 'guard_name' => 'web']);
            }

            $deleted = 0;

            if ($prune) {
                $deleted = Permission::query()->where('guard_name', 'web')->whereNotIn('name', $wanted)->delete();
            }

            $this->registrar->forgetCachedPermissions();

            return ['created' => count($missing), 'deleted' => (int) $deleted];
        });
    }
}
