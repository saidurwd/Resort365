<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Modules\Core\Models\NotificationTemplate;

/**
 * Goes back to a template's default wording: the tenant's own versions (every locale) are deleted.
 */
class ResetNotificationTemplate extends Action
{
    public function handle(string $key, string $channel): int
    {
        $deleted = 0;

        // One by one, so the audit log records each deleted version.
        foreach (NotificationTemplate::query()->where('key', $key)->where('channel', $channel)->get() as $template) {
            $template->delete();
            $deleted++;
        }

        return $deleted;
    }
}
