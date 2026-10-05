<?php

namespace Modules\IAM\Contracts;

use Modules\IAM\DTOs\RoleSummary;

/**
 * The current tenant's roles, for modules that keep settings per role (e.g. Restaurant's maximum
 * discount %, Step 3.6).
 */
interface RoleDirectory
{
    /**
     * Every role of the tenant, system roles first, then by name.
     *
     * @return list<RoleSummary>
     */
    public function all(): array;

    /**
     * The ids of the roles a user holds.
     *
     * @return list<int>
     */
    public function roleIdsOf(int $userId): array;
}
