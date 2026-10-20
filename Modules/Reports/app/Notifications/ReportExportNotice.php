<?php

namespace Modules\Reports\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Core\Contracts\NotificationTemplates;

/**
 * In-app notice that a report file is ready, or could not be made (Step 7.1). Wording: templates reports.export.ready and
 * reports.export.failed.
 */
class ReportExportNotice extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $format,
        public readonly string $url,
        public readonly bool $ready,
        public readonly string $reason = '',
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
        $template = app(NotificationTemplates::class)->render($this->ready ? 'reports.export.ready' : 'reports.export.failed', 'database', ['report' => $this->title, 'format' => $this->format, 'reason' => $this->reason]);

        return ['title' => (string) $template->subject, 'body' => $template->body, 'icon' => $this->ready ? 'bi-file-earmark-arrow-down' : 'bi-exclamation-triangle', 'url' => $this->url];
    }
}
