<?php

namespace Modules\Platform\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Platform\Actions\DeleteAnnouncement;
use Modules\Platform\Actions\SaveAnnouncement;
use Modules\Platform\Enums\AnnouncementAudience;
use Modules\Platform\Enums\AnnouncementLevel;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Http\Requests\AnnouncementRequest;
use Modules\Platform\Models\Announcement;
use Modules\Platform\Models\Plan;

/**
 * Platform console → Announcements (Step 7.3): messages shown at the top of the tenants' pages for a while.
 */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('platform::announcements.index', ['announcements' => Announcement::query()->latest('starts_at')->limit(100)->get()]);
    }

    public function create(): View
    {
        return $this->form(new Announcement(['level' => AnnouncementLevel::Info, 'audience' => AnnouncementAudience::All, 'starts_at' => now()]));
    }

    public function store(AnnouncementRequest $request, SaveAnnouncement $save): RedirectResponse
    {
        try {
            $save->handle(null, $this->data($request), $request->user('platform'));
        } catch (PlatformRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('platform.announcements.index')->with('success', __('The announcement is made.'));
    }

    public function edit(Announcement $announcement): View
    {
        return $this->form($announcement);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement, SaveAnnouncement $save): RedirectResponse
    {
        try {
            $save->handle($announcement, $this->data($request), $request->user('platform'));
        } catch (PlatformRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('platform.announcements.index')->with('success', __('The announcement is saved.'));
    }

    public function destroy(Request $request, Announcement $announcement, DeleteAnnouncement $delete): RedirectResponse
    {
        $delete->handle($announcement, $request->user('platform'));

        return to_route('platform.announcements.index')->with('success', __('The announcement is deleted.'));
    }

    private function form(Announcement $announcement): View
    {
        return view('platform::announcements.form', [
            'announcement' => $announcement, 'levels' => AnnouncementLevel::options(), 'audiences' => AnnouncementAudience::options(),
            'plans' => Plan::query()->orderBy('sort_order')->pluck('name', 'id')->all(), 'tenants' => Tenant::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    /**
     * @return array{title: string, body: string, level: string, audience: string, plan_ids: list<int>, tenant_ids: list<int>, starts_at: string|null, ends_at: string|null}
     */
    private function data(AnnouncementRequest $request): array
    {
        $v = $request->validated();

        return [
            'title' => (string) $v['title'], 'body' => (string) $v['body'], 'level' => (string) $v['level'], 'audience' => (string) $v['audience'], 'plan_ids' => array_map(intval(...), $v['plan_ids'] ?? []),
            'tenant_ids' => array_map(intval(...), $v['tenant_ids'] ?? []), 'starts_at' => $v['starts_at'] ?? null, 'ends_at' => $v['ends_at'] ?? null,
        ];
    }
}
