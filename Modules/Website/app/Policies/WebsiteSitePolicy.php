<?php

namespace Modules\Website\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Website\Models\WebsiteSite;

/**
 * The website's back office (Step 8.2a): `website.content.view` sees it (and previews an unpublished site), `website.content.manage`
 * changes it and its photos.
 */
class WebsiteSitePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('website.content.view');
    }

    public function view(Authenticatable&Authorizable $user, WebsiteSite $site): bool
    {
        return $user->can('website.content.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('website.content.manage');
    }

    public function update(Authenticatable&Authorizable $user, WebsiteSite $site): bool
    {
        return $user->can('website.content.manage');
    }
}
