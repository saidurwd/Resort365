<?php

namespace Modules\Restaurant\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Restaurant\Models\Printer;

/**
 * Printers are part of outlet setup (restaurant.outlet.view / .manage).
 */
class PrinterPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('restaurant.outlet.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('restaurant.outlet.manage');
    }

    public function update(Authenticatable&Authorizable $user, Printer $printer): bool
    {
        return $user->can('restaurant.outlet.manage');
    }

    public function delete(Authenticatable&Authorizable $user, Printer $printer): bool
    {
        return $user->can('restaurant.outlet.manage');
    }
}
