<?php

namespace Modules\Guest\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Models\Guest;

/**
 * Puts a guest on the blacklist with a reason (who and when are recorded).
 */
class BlacklistGuest extends Action
{
    public function handle(Guest $guest, string $reason, ?int $userId): Guest
    {
        $guest->forceFill([
            'is_blacklisted' => true,
            'blacklist_reason' => $reason,
            'blacklisted_at' => now(),
            'blacklisted_by' => $userId,
        ])->save();

        return $guest;
    }
}
