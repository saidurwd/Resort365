<?php

namespace Modules\Core\Contracts;

use Illuminate\Notifications\Notification;

/**
 * The people side of approvals (Step 5.1), implemented by IAM so Core does not depend on it: who may decide,
 * what they are called, and how they are told.
 */
interface ApprovalParticipants
{
    public function userCan(int $userId, string $permission): bool;

    /**
     * Active users who hold the permission.
     *
     * @return list<int>
     */
    public function holders(string $permission): array;

    /**
     * Every active user, for choosing a delegate.
     *
     * @return array<int, string> user id => name
     */
    public function colleagues(): array;

    /**
     * @param  list<int>  $userIds
     * @return array<int, string> user id => name
     */
    public function names(array $userIds): array;

    /**
     * @param  list<int>  $userIds
     */
    public function notify(array $userIds, Notification $notification): void;
}
