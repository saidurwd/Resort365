<?php

namespace Modules\Accounting\Services;

/**
 * The key of a budget line in the maps the budget report works with: an account and a department (0 for none) (Step 7.2).
 */
final class BudgetKey
{
    public static function of(int $accountId, ?int $departmentId): string
    {
        return $accountId.':'.($departmentId ?? 0);
    }

    /**
     * @return array{0: int, 1: int|null}
     */
    public static function split(string $key): array
    {
        [$account, $department] = array_map(intval(...), explode(':', $key));

        return [$account, $department > 0 ? $department : null];
    }
}
