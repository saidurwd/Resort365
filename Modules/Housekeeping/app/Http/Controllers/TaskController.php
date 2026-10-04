<?php

namespace Modules\Housekeeping\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Housekeeping\Actions\AssignTask;
use Modules\Housekeeping\Actions\CreateDailyTasks;
use Modules\Housekeeping\Actions\ProgressTask;
use Modules\Housekeeping\Enums\TaskStatus;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Http\Controllers\Concerns\HousekeepingScreen;
use Modules\Housekeeping\Http\Requests\AssignTasksRequest;
use Modules\Housekeeping\Http\Requests\ProgressTaskRequest;
use Modules\Housekeeping\Models\HousekeepingTask;
use Modules\Housekeeping\Services\TaskTransitions;

/**
 * Housekeeping → Tasks (supervisors: the day's tasks, assigning, inspecting, the daily round) and
 * My tasks (attendants: start, finish, skip).
 */
class TaskController extends Controller
{
    use HousekeepingScreen;

    public function index(TaskTransitions $transitions): View
    {
        Gate::authorize('viewAny', HousekeepingTask::class);
        $property = $this->property();

        return view('housekeeping::tasks.index', [
            'property' => $property,
            'date' => CarbonImmutable::parse($property->businessDate),
            'tasks' => HousekeepingTask::query()->where('property_id', $property->id)->where('business_date', $property->businessDate)->orderBy('status')->orderBy('id')->get(),
            'open' => HousekeepingTask::query()->where('property_id', $property->id)->where('business_date', '<', $property->businessDate)
                ->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])->orderBy('business_date')->get(),
            'rooms' => $this->roomOptions($property->id),
            'attendants' => $this->staffWith('housekeeping.task.perform'),
            'names' => $this->userNames(),
            'transitions' => $transitions,
        ]);
    }

    public function mine(TaskTransitions $transitions): View
    {
        Gate::authorize('housekeeping.task.perform');
        $property = $this->property();

        return view('housekeeping::tasks.mine', [
            'property' => $property,
            'tasks' => HousekeepingTask::query()->where('property_id', $property->id)->where('business_date', '<=', $property->businessDate)
                ->where(fn ($query) => $query->where('assigned_to', $this->userId())->orWhereNull('assigned_to'))
                ->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])->orderByRaw('assigned_to is null')->orderBy('business_date')->orderBy('id')->get(),
            'rooms' => $this->roomOptions($property->id),
            'transitions' => $transitions,
        ]);
    }

    public function generate(CreateDailyTasks $daily): RedirectResponse
    {
        Gate::authorize('manage', HousekeepingTask::class);
        $property = $this->property();
        $rooms = $daily->handle($property->id, $property->businessDate, $this->userId());

        return to_route('housekeeping.tasks.index')->with('success', trans_choice(':count room in house is on today\'s round.|:count rooms in house are on today\'s round.', $rooms));
    }

    public function assign(AssignTasksRequest $request, AssignTask $assign): RedirectResponse
    {
        try {
            $count = $assign->handle(array_map(intval(...), (array) $request->validated('task_ids')), $request->filled('attendant_id') ? (int) $request->validated('attendant_id') : null);
        } catch (HousekeepingNotPossible $exception) {
            return to_route('housekeeping.tasks.index')->with('error', $exception->getMessage());
        }

        return to_route('housekeeping.tasks.index')->with('success', trans_choice(':count task assigned.|:count tasks assigned.', $count));
    }

    public function step(ProgressTaskRequest $request, HousekeepingTask $task, string $step, ProgressTask $progress): RedirectResponse
    {
        try {
            $progress->handle($task, $step, $this->userId(), $request->validated('note'));
        } catch (HousekeepingNotPossible $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', match ($step) {
            'start' => __('Cleaning started.'),
            'finish' => __('Room cleaned.'),
            'pass' => __('Inspection passed.'),
            'fail' => __('Inspection failed: the room is dirty again and the task is back on the list.'),
            default => __('Task skipped.'),
        });
    }
}
