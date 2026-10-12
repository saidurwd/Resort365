<?php

namespace Modules\Procurement\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Purchase orders: procurement.order.view to look and download the PDF, .create to write, submit and cancel, .send to email.
 */
class OrderPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.order.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $order = null): bool
    {
        return $user->can('procurement.order.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.order.create');
    }

    public function update(Authenticatable&Authorizable $user, mixed $order = null): bool
    {
        return $user->can('procurement.order.create');
    }

    public function send(Authenticatable&Authorizable $user, mixed $order = null): bool
    {
        return $user->can('procurement.order.send');
    }
}
