<?php

namespace Modules\Accounting\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Accounting\Models\Budget;

/**
 * Budgets: accounting.budget.view to look at them and the budget against actual, accounting.budget.manage to make, change and
 * delete them.
 */
class BudgetPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accounting.budget.view');
    }

    public function view(Authenticatable&Authorizable $user, Budget $budget): bool
    {
        return $user->can('accounting.budget.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('accounting.budget.manage');
    }

    public function update(Authenticatable&Authorizable $user, Budget $budget): bool
    {
        return $user->can('accounting.budget.manage');
    }

    public function delete(Authenticatable&Authorizable $user, Budget $budget): bool
    {
        return $user->can('accounting.budget.manage');
    }
}
