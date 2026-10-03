<?php

namespace Modules\Guest\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Models\Guest;

/**
 * Takes a guest off the blacklist (the change history keeps the old reason).
 */
class ClearBlacklist extends Action
{
    public function handle(Guest $guest): Guest
    {
        $guest->forceFill(['is_blacklisted' => false, 'blacklist_reason' => null, 'blacklisted_at' => null, 'blacklisted_by' => null])->save();

        return $guest;
    }
}
