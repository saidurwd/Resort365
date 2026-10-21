<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Modules\Platform\Models\Announcement;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Services\PlatformAudit;

/**
 * Deletes an announcement (Step 7.3); tenants stop seeing it at once.
 */
class DeleteAnnouncement extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    public function handle(Announcement $announcement, ?PlatformAdmin $admin = null): void
    {
        $this->transaction(function () use ($announcement, $admin): void {
            $this->audit->log($admin, 'announcement.deleted', __('Announcement ":title" deleted', ['title' => $announcement->title]), null, ['announcement' => $announcement->id]);
            $announcement->delete();
        });
    }
}
