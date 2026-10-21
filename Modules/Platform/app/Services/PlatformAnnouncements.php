<?php

namespace Modules\Platform\Services;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Modules\Core\Contracts\Announcements;
use Modules\Core\DTOs\AnnouncementSummary;
use Modules\Platform\Enums\AnnouncementAudience;
use Modules\Platform\Models\Announcement;

/**
 * The announcements that apply to the current tenant now (Step 7.3): running, and meant for everyone, for its plan or for it.
 * The most urgent come first.
 */
class PlatformAnnouncements implements Announcements
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function active(): array
    {
        $tenant = $this->tenant->tenant();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        return Announcement::query()->where('starts_at', '<=', now())->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->get()
            ->filter(fn (Announcement $announcement): bool => $this->reaches($announcement, $tenant))
            ->sortBy(fn (Announcement $announcement): string => ['danger' => 0, 'warning' => 1, 'info' => 2][$announcement->level->value].'-'.str_pad((string) (PHP_INT_MAX - $announcement->id), 20, '0', STR_PAD_LEFT))
            ->map(fn (Announcement $announcement): AnnouncementSummary => new AnnouncementSummary($announcement->id, $announcement->title, $announcement->body, $announcement->level->value))->values()->all();
    }

    private function reaches(Announcement $announcement, Tenant $tenant): bool
    {
        return match ($announcement->audience) {
            AnnouncementAudience::All => true,
            AnnouncementAudience::Plans => $tenant->plan_id !== null && in_array($tenant->plan_id, $announcement->plan_ids ?? [], true),
            AnnouncementAudience::Tenants => in_array($tenant->id, $announcement->tenant_ids ?? [], true),
        };
    }
}
