<?php

namespace Modules\Guest\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Guest\Models\Company;

class CompanyPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guest.company.view');
    }

    public function view(Authenticatable&Authorizable $user, Company $company): bool
    {
        return $user->can('guest.company.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guest.company.manage');
    }

    public function update(Authenticatable&Authorizable $user, Company $company): bool
    {
        return $user->can('guest.company.manage');
    }

    public function delete(Authenticatable&Authorizable $user, Company $company): bool
    {
        return $user->can('guest.company.manage');
    }
}
