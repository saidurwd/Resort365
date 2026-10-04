<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Modules\Core\Models\NotificationTemplate;

/**
 * Saves the tenant's own wording of a registered notification template (one row per key,
 * channel and locale). NotificationTemplates::render() uses it instead of the default.
 */
class SaveNotificationTemplate extends Action
{
    public function handle(string $key, string $channel, string $locale, ?string $subject, string $body, bool $active = true): NotificationTemplate
    {
        $template = NotificationTemplate::query()->where('key', $key)->where('channel', $channel)->where('locale', $locale)->first()
            ?? new NotificationTemplate(['key' => $key, 'channel' => $channel, 'locale' => $locale]);

        $template->fill(['subject' => $subject, 'body' => $body, 'is_active' => $active])->save();

        return $template;
    }
}
