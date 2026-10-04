<?php

namespace Modules\Reservation\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Core\Contracts\NotificationTemplates;

/**
 * In-app notice to the staff member who made a booking whose deposit hold expired (ARCHITECTURE
 * §6.5 rule 4). Wording: template reservation.hold_expired_staff.
 */
class HoldExpiredNotice extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly string $guest,
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
        $template = app(NotificationTemplates::class)->render('reservation.hold_expired_staff', 'database', ['code' => $this->code, 'guest' => $this->guest]);

        return ['title' => (string) $template->subject, 'body' => $template->body, 'icon' => 'bi-hourglass-bottom', 'url' => $this->url];
    }
}
