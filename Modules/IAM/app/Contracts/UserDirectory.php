<?php

namespace Modules\IAM\Contracts;

use Illuminate\Notifications\Notification;
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

    /**
     * Send a notification to active users of the current tenant (e.g. an in-app notice to the
     * staff member who made a booking). Unknown or inactive users are skipped.
     *
     * @param  list<int>  $userIds
     */
    public function notify(array $userIds, Notification $notification): void;
}
