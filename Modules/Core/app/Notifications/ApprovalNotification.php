<?php

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Core\Contracts\NotificationTemplates;

/**
 * In-app message about an approval request (Step 5.1): `requested` to the approvers of a step, `approved`,
 * `rejected` and `cancelled` to the person who asked. Templates `core.approval.{kind}`.
 */
class ApprovalNotification extends Notification
{
    public function __construct(
        public readonly string $kind,
        public readonly string $reference,
        public readonly string $amount,
        public readonly string $person,
        public readonly string $comment,
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
        $template = app(NotificationTemplates::class)->render('core.approval.'.$this->kind, 'database', [
            'reference' => $this->reference, 'amount' => $this->amount, 'person' => $this->person, 'comment' => $this->comment,
        ]);

        return [
            'title' => (string) $template->subject, 'body' => $template->body, 'url' => $this->url,
            'icon' => match ($this->kind) {
                'approved' => 'bi-check-circle', 'rejected' => 'bi-x-circle', 'cancelled' => 'bi-slash-circle', default => 'bi-check2-square'
            },
        ];
    }
}
