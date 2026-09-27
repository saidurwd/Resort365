<?php

namespace Modules\IAM\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email invitation to join a tenant. The link is signed and expires (see SendInvitation).
 */
class UserInvitation extends Notification
{
    public function __construct(
        public readonly string $url,
        public readonly string $tenantName,
        public readonly ?string $inviterName,
        public readonly int $expiresInDays,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You are invited to :tenant on :app', ['tenant' => $this->tenantName, 'app' => config('app.name')]))
            ->greeting(__('Hello!'))
            ->line($this->inviterName
                ? __(':inviter invited you to join :tenant.', ['inviter' => $this->inviterName, 'tenant' => $this->tenantName])
                : __('You are invited to join :tenant.', ['tenant' => $this->tenantName]))
            ->action(__('Accept invitation'), $this->url)
            ->line(__('This link expires in :days days. If you did not expect this invitation, you can ignore this email.', ['days' => $this->expiresInDays]));
    }
}
