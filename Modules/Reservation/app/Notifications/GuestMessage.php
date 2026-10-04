<?php

namespace Modules\Reservation\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An email to a guest whose wording comes from a template (already filled in). The first line of
 * the body is the greeting, each further line a line of the email; PDFs (voucher, quote) are attached.
 */
class GuestMessage extends Notification
{
    /**
     * @param  array<string, string>  $attachments  file name => PDF bytes
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $body,
        public readonly string $signature,
        public readonly array $attachments = [],
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

        // The template's first line ("Dear Ayesha,") is the greeting.
        $message = (new MailMessage)->subject($this->subject)->greeting(array_shift($lines) ?? '')->salutation(__('Regards,')."\n".$this->signature);

        foreach ($lines as $line) {
            $message->line($line);
        }

        foreach ($this->attachments as $name => $pdf) {
            $message->attachData($pdf, $name, ['mime' => 'application/pdf']);
        }

        return $message;
    }
}
