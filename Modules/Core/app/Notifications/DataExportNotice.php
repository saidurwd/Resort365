<?php

namespace Modules\Core\Notifications;

use Illuminate\Notifications\Notification;
use Modules\Core\Jobs\GenerateTenantExport;

/**
 * In-app notice that a company's data export is ready, or failed (Step 7.4).
 */
class DataExportNotice extends Notification
{
    public function __construct(
        public readonly bool $ready,
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
        return $this->ready
            ? ['title' => __('Your data export is ready'), 'body' => __('Download it within :days days.', ['days' => GenerateTenantExport::KEEP_DAYS]), 'icon' => 'bi-file-earmark-zip', 'url' => $this->url]
            : ['title' => __('Your data export failed'), 'body' => __('Please try again, or contact support.'), 'icon' => 'bi-exclamation-triangle', 'url' => $this->url];
    }
}
