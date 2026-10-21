<?php

namespace Modules\Platform\Actions;

use App\Models\Tenant;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Platform\Enums\AnnouncementAudience;
use Modules\Platform\Enums\AnnouncementLevel;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\Announcement;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Services\PlatformAudit;

/**
 * Makes or changes an announcement (Step 7.3): a title and message, how urgent, when it runs and who sees it. An announcement
 * for plans or tenants needs at least one of them chosen.
 */
class SaveAnnouncement extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @param  array{title: string, body: string, level: string, audience: string, plan_ids?: list<int>, tenant_ids?: list<int>, starts_at?: string|null, ends_at?: string|null}  $data
     *
     * @throws PlatformRuleViolated
     */
    public function handle(?Announcement $announcement, array $data, ?PlatformAdmin $admin = null): Announcement
    {
        $audience = AnnouncementAudience::from($data['audience']);
        $plans = array_values(array_unique(array_map(intval(...), $data['plan_ids'] ?? [])));
        $tenants = array_values(array_unique(array_map(intval(...), $data['tenant_ids'] ?? [])));

        if ($audience === AnnouncementAudience::Plans && ($plans === [] || Plan::query()->whereIn('id', $plans)->count() !== count($plans))) {
            throw new PlatformRuleViolated(__('Choose the plans that should see this.'));
        }

        if ($audience === AnnouncementAudience::Tenants && ($tenants === [] || Tenant::query()->whereIn('id', $tenants)->count() !== count($tenants))) {
            throw new PlatformRuleViolated(__('Choose the tenants that should see this.'));
        }

        $starts = ($data['starts_at'] ?? null) !== null && $data['starts_at'] !== '' ? CarbonImmutable::parse($data['starts_at']) : CarbonImmutable::now();
        $ends = ($data['ends_at'] ?? null) !== null && $data['ends_at'] !== '' ? CarbonImmutable::parse($data['ends_at']) : null;

        if ($ends instanceof CarbonImmutable && $ends->lte($starts)) {
            throw new PlatformRuleViolated(__('An announcement ends after it starts.'));
        }

        return $this->transaction(function () use ($announcement, $data, $audience, $plans, $tenants, $starts, $ends, $admin): Announcement {
            $created = ! $announcement instanceof Announcement;
            $announcement ??= new Announcement(['created_by' => $admin?->id]);
            $announcement->fill([
                'title' => trim($data['title']), 'body' => trim($data['body']), 'level' => AnnouncementLevel::from($data['level']), 'audience' => $audience,
                'plan_ids' => $audience === AnnouncementAudience::Plans ? $plans : null, 'tenant_ids' => $audience === AnnouncementAudience::Tenants ? $tenants : null, 'starts_at' => $starts, 'ends_at' => $ends,
            ])->save();
            $this->audit->log($admin, $created ? 'announcement.created' : 'announcement.updated', ($created ? __('Announcement ":title" made', ['title' => $announcement->title]) : __('Announcement ":title" changed', ['title' => $announcement->title])), null, ['announcement' => $announcement->id]);

            return $announcement;
        });
    }
}
