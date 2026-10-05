<?php

namespace Modules\Restaurant\Services;

use Modules\IAM\Contracts\RoleDirectory;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Restaurant\Models\DiscountLimit;

/**
 * The largest discount % a person may give without a manager (ARCHITECTURE §5.10.7): the highest
 * limit of their roles; anyone with restaurant.discount.approve may give any discount.
 */
class DiscountLimits
{
    public function __construct(
        private readonly RoleDirectory $roles,
        private readonly UserDirectory $users,
    ) {}

    public function maxPercent(int $userId): string
    {
        if ($this->users->userCan($userId, 'restaurant.discount.approve')) {
            return '100.00';
        }

        $limit = DiscountLimit::query()->whereIn('role_id', $this->roles->roleIdsOf($userId))->max('max_percent');

        return number_format((float) ($limit ?? 0), 2, '.', '');
    }

    /**
     * @return array<int, string> role id => max %
     */
    public function all(): array
    {
        return DiscountLimit::query()->pluck('max_percent', 'role_id')->map(fn ($percent): string => (string) $percent)->all();
    }
}
