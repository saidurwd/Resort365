<?php

namespace Modules\Billing\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email with a payment link (Step 8.3): the template's text (Setup → Email templates, `billing.payment_link`) and a Pay button.
 */
class PaymentLinkMessage extends Notification
{
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
        public readonly string $link,
        public readonly string $signature,
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
        $lines = array_values(array_filter(array_map(trim(...), preg_split('/\R/', trim($this->body)) ?: []), fn (string $line): bool => $line !== ''));
        $message = (new MailMessage)->subject($this->subject)->greeting(array_shift($lines) ?? '')->salutation(__('Regards,')."\n".$this->signature);

        foreach ($lines as $line) {
            $message->line($line);
        }

        return $message->action(__('Pay now'), $this->link);
    }
}
