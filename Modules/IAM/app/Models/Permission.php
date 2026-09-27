<?php

namespace Modules\IAM\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * A permission (central: one global list, registered by modules in the PermissionRegistry
 * and stored by `php artisan permissions:sync`).
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 */
class Permission extends SpatiePermission {}
