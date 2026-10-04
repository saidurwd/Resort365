<?php

namespace Modules\Housekeeping\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Housekeeping\Actions\CreateDueWorkOrders;
use Modules\Housekeeping\Actions\SaveSchedule;
use Modules\Housekeeping\Enums\WorkOrderCategory;
use Modules\Housekeeping\Http\Controllers\Concerns\HousekeepingScreen;
use Modules\Housekeeping\Http\Requests\ScheduleRequest;
use Modules\Housekeeping\Models\MaintenanceSchedule;

/**
 * Housekeeping → Preventive maintenance: the current property's schedules, adding and changing
 * them, and opening the work orders that are due now.
 */
class ScheduleController extends Controller
{
    use HousekeepingScreen;

    public function index(): View
    {
        Gate::authorize('viewAny', MaintenanceSchedule::class);
        $property = $this->property();

        return view('housekeeping::schedules.index', [
            'property' => $property,
            'schedules' => MaintenanceSchedule::query()->where('property_id', $property->id)->orderByDesc('is_active')->orderBy('next_due_on')->get(),
            'rooms' => $this->roomOptions($property->id),
            'names' => $this->userNames(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', MaintenanceSchedule::class);

        return $this->form(null);
    }

    public function store(ScheduleRequest $request, SaveSchedule $save): RedirectResponse
    {
        $save->handle($this->property()->id, null, $this->data($request));

        return to_route('housekeeping.schedules.index')->with('success', __('Schedule saved.'));
    }

    public function edit(MaintenanceSchedule $schedule): View
    {
        Gate::authorize('update', $schedule);

        return $this->form($schedule);
    }

    public function update(ScheduleRequest $request, MaintenanceSchedule $schedule, SaveSchedule $save): RedirectResponse
    {
        Gate::authorize('update', $schedule);
        $save->handle($schedule->property_id, $schedule, $this->data($request));

        return to_route('housekeeping.schedules.index')->with('success', __('Schedule saved.'));
    }

    public function run(CreateDueWorkOrders $create): RedirectResponse
    {
        Gate::authorize('create', MaintenanceSchedule::class);
        $property = $this->property();
        $opened = $create->handle($property->id, $property->businessDate);

        return to_route('housekeeping.schedules.index')->with('success', trans_choice(':count work order opened.|:count work orders opened.', $opened));
    }

    private function form(?MaintenanceSchedule $schedule): View
    {
        $property = $this->property();

        return view('housekeeping::schedules.form', [
            'schedule' => $schedule,
            'property' => $property,
            'rooms' => $this->roomOptions($property->id),
            'categories' => WorkOrderCategory::options(),
            'technicians' => $this->staffWith('housekeeping.work-order.view'),
        ]);
    }

    /**
     * @return array{title: string, category: string, room_id: int|null, location: string|null, interval_days: int, next_due_on: string, assigned_to: int|null, is_active: bool, notes: string|null}
     */
    private function data(ScheduleRequest $request): array
    {
        return [
            'title' => (string) $request->validated('title'),
            'category' => (string) $request->validated('category'),
            'room_id' => $request->filled('room_id') ? (int) $request->validated('room_id') : null,
            'location' => $request->validated('location'),
            'interval_days' => (int) $request->validated('interval_days'),
            'next_due_on' => (string) $request->validated('next_due_on'),
            'assigned_to' => $request->filled('assigned_to') ? (int) $request->validated('assigned_to') : null,
            'is_active' => $request->boolean('is_active'),
            'notes' => $request->validated('notes'),
        ];
    }
}
