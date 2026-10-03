<?php

namespace Modules\Guest\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Guest\Models\TravelAgent;

class TravelAgentPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guest.travel-agent.view');
    }

    public function view(Authenticatable&Authorizable $user, TravelAgent $travelAgent): bool
    {
        return $user->can('guest.travel-agent.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('guest.travel-agent.manage');
    }

    public function update(Authenticatable&Authorizable $user, TravelAgent $travelAgent): bool
    {
        return $user->can('guest.travel-agent.manage');
    }

    public function delete(Authenticatable&Authorizable $user, TravelAgent $travelAgent): bool
    {
        return $user->can('guest.travel-agent.manage');
    }
}
