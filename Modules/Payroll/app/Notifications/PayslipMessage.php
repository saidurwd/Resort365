<?php

namespace Modules\Payroll\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email an employee gets with their payslip PDF (Step 6.5).
 */
class PayslipMessage extends Notification
{
    public function __construct(
        public readonly string $name,
        public readonly string $month,
        public readonly string $property,
        public readonly string $fileName,
        public readonly string $pdf,
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
        return (new MailMessage)->subject(__('Your payslip for :month', ['month' => $this->month]))->greeting(__('Dear :name,', ['name' => $this->name]))
            ->line(__('Your payslip for :month is attached.', ['month' => $this->month]))->line(__('If anything looks wrong, please speak to the payroll office.'))
            ->salutation(__('Regards,')."\n".$this->property)->attachData($this->pdf, $this->fileName, ['mime' => 'application/pdf']);
    }
}
