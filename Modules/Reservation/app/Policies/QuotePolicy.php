<?php

namespace Modules\Reservation\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Reservation\Models\Quote;

/**
 * Quotes of properties the user cannot access never load (BelongsToProperty).
 */
class QuotePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('reservation.quote.view');
    }

    public function view(Authenticatable&Authorizable $user, Quote $quote): bool
    {
        return $user->can('reservation.quote.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('reservation.quote.create');
    }

    /**
     * Email, decline or book the quote.
     */
    public function update(Authenticatable&Authorizable $user, Quote $quote): bool
    {
        return $user->can('reservation.quote.create');
    }
}
