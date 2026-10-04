<?php

namespace Modules\FrontOffice\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\FrontOffice\Models\NightAudit;

/**
 * Night audits of properties the user cannot access never load (BelongsToProperty).
 */
class NightAuditPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('frontoffice.audit.view');
    }

    public function view(Authenticatable&Authorizable $user, NightAudit $audit): bool
    {
        return $user->can('frontoffice.audit.view');
    }

    public function run(Authenticatable&Authorizable $user): bool
    {
        return $user->can('frontoffice.audit.run');
    }
}
