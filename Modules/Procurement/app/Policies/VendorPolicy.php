<?php

namespace Modules\Procurement\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Vendors: procurement.vendor.view to look and download documents, .manage to change vendors, contacts, prices and documents.
 */
class VendorPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.vendor.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $vendor = null): bool
    {
        return $user->can('procurement.vendor.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('procurement.vendor.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $vendor = null): bool
    {
        return $user->can('procurement.vendor.manage');
    }
}
