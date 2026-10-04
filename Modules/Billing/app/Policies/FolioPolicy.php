<?php

namespace Modules\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Billing\Models\Folio;

/**
 * Folios of properties the user cannot access never load (BelongsToProperty).
 */
class FolioPolicy
{
    public function view(Authenticatable&Authorizable $user, Folio $folio): bool
    {
        return $user->can('billing.folio.view');
    }

    public function post(Authenticatable&Authorizable $user, Folio $folio): bool
    {
        return $user->can('billing.folio.post');
    }

    public function adjust(Authenticatable&Authorizable $user, Folio $folio): bool
    {
        return $user->can('billing.folio.adjust');
    }

    public function void(Authenticatable&Authorizable $user, Folio $folio): bool
    {
        return $user->can('billing.folio.void');
    }
}
