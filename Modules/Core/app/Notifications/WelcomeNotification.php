<?php

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Core\Contracts\NotificationTemplates;

/**
 * In-app welcome message (template `core.welcome`). Data shape used by the navbar bell:
 * title, body, icon, url.
 */
class WelcomeNotification extends Notification
{
    public function __construct(
        public readonly string $name,
        public readonly string $tenantName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, icon: string, url: ?string}
     */
    public function toArray(object $notifiable): array
    {
        $template = app(NotificationTemplates::class)->render('core.welcome', 'database', ['name' => $this->name, 'tenant' => $this->tenantName]);

        return [
            'title' => (string) $template->subject,
            'body' => $template->body,
            'icon' => 'bi-stars',
            'url' => null,
        ];
    }
}
