<?php

namespace Modules\FrontOffice\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Core\Contracts\NotificationTemplates;

/**
 * In-app notice to the people who run the night audit when the scheduled audit could not run
 * (departures still in house). Wording: template frontoffice.night_audit_blocked.
 */
class NightAuditBlockedNotice extends Notification
{
    public function __construct(
        public readonly string $property,
        public readonly string $date,
        public readonly string $reasons,
        public readonly string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, icon: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $template = app(NotificationTemplates::class)->render('frontoffice.night_audit_blocked', 'database',
            ['property' => $this->property, 'date' => $this->date, 'reasons' => $this->reasons]);

        return ['title' => (string) $template->subject, 'body' => $template->body, 'icon' => 'bi-moon-stars', 'url' => $this->url];
    }
}
