<?php

namespace Modules\HR\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\HR\Actions\AssignRoster;
use Modules\HR\Actions\ClearRoster;
use Modules\HR\Actions\CopyRosterWeek;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Http\Controllers\Concerns\HrScreen;
use Modules\HR\Http\Requests\AttendanceReportRequest;
use Modules\HR\Http\Requests\RosterRequest;
use Modules\HR\Models\Roster;
use Modules\HR\Models\Shift;
use Modules\HR\Services\RosterGrid;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * HR → Roster: a week, two weeks or a month of the current property's employees by shift; assign, clear and copy a week.
 */
class RosterController extends Controller
{
    use HrScreen;

    public function index(AttendanceReportRequest $request, RosterGrid $grid, DepartmentDirectory $departments): View
    {
        Gate::authorize('viewAny', Roster::class);
        $from = CarbonImmutable::parse((string) $request->validated('from', $this->today()))->startOfWeek(CarbonImmutable::SUNDAY)->toDateString();
        $days = (int) $request->validated('days', 14);
        $department = $request->filled('department') ? (int) $request->validated('department') : null;

        return view('hr::roster.index', [
            'grid' => $grid->build($this->propertyId(), $department, $from, $days), 'from' => $from, 'days' => $days, 'department' => $department, 'today' => $this->today(),
            'departments' => collect($departments->all())->pluck('name', 'id')->all(), 'shifts' => Shift::query()->where('is_active', true)->orderBy('starts_at')->get(),
            'canManage' => $request->user()?->can('hr.roster.manage') ?? false, 'subtitle' => $this->propertyName(),
        ]);
    }

    public function update(RosterRequest $request, string $action): RedirectResponse
    {
        Gate::authorize('manage', Roster::class);
        $data = $request->validated();
        $ids = array_map(intval(...), $data['employee_ids']);

        try {
            $message = match ($action) {
                'assign' => trans_choice(':count day rostered.|:count days rostered.', app(AssignRoster::class)->handle($ids, $data['from'], $data['to'], isset($data['shift_id']) && $data['shift_id'] !== '' ? (int) $data['shift_id'] : null,
                    isset($data['weekdays']) ? array_map(intval(...), $data['weekdays']) : null)),
                'clear' => trans_choice(':count roster entry removed.|:count roster entries removed.', app(ClearRoster::class)->handle($ids, $data['from'], $data['to'])),
                default => trans_choice(':count day copied.|:count days copied.', app(CopyRosterWeek::class)->handle($ids, $data['week_start'], (int) $data['weeks'])),
            };
        } catch (HrRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', $message);
    }
}
