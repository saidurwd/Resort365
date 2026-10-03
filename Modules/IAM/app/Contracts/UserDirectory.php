<?php

namespace Modules\IAM\Contracts;

use Modules\IAM\DTOs\UserSummary;

/**
 * The current tenant's users, for other modules (e.g. property access assignment).
 */
interface UserDirectory
{
    /**
     * Invited and active users, ordered by name.
     *
     * @return list<UserSummary>
     */
    public function all(): array;

    /**
     * Whether the user holds the permission (for defaults such as "sees every property").
     */
    public function userCan(int $userId, string $permission): bool;
}
