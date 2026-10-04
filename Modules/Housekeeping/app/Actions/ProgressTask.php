<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Models\HousekeepingTask;
use Modules\Housekeeping\Services\RoomStatusChanger;
use Modules\Housekeeping\Services\TaskTransitions;
use Modules\Property\Enums\HousekeepingStatus;

/**
 * Moves a cleaning task one step (TaskTransitions): start, finish (room Clean), pass the inspection
 * (room Inspected), fail it (room Dirty, task back to Pending) or skip it.
 */
class ProgressTask extends Action
{
    public function __construct(
        private readonly TaskTransitions $transitions,
        private readonly RoomStatusChanger $statuses,
    ) {}

    /**
     * @throws HousekeepingNotPossible
     */
    public function handle(HousekeepingTask $task, string $step, ?int $userId = null, ?string $note = null): HousekeepingTask
    {
        return $this->transaction(function () use ($task, $step, $userId, $note): HousekeepingTask {
            $locked = HousekeepingTask::query()->lockForUpdate()->findOrFail($task->id);

            if (! $this->transitions->allowed($locked->status, $step)) {
                throw new HousekeepingNotPossible(__('This task is :status.', ['status' => strtolower($locked->status->label())]));
            }

            $now = now();
            $locked->forceFill(array_filter([
                'status' => $this->transitions->next($step),
                'started_at' => in_array($step, ['start', 'finish'], true) ? ($locked->started_at ?? $now) : null,
                'finished_at' => $step === 'finish' ? $now : null,
                'inspected_at' => in_array($step, ['pass', 'fail'], true) ? $now : null,
                'inspected_by' => in_array($step, ['pass', 'fail'], true) ? $userId : null,
                'assigned_to' => $step === 'start' && $locked->assigned_to === null ? $userId : null,
                'notes' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            ], fn ($value): bool => $value !== null))->save();

            if ($step === 'fail') {
                $locked->forceFill(['finished_at' => null, 'started_at' => null])->save();
            }

            $roomStatus = $this->transitions->roomStatus($step);

            if ($roomStatus instanceof HousekeepingStatus) {
                $this->statuses->change($locked->property_id, [$locked->room_id], $roomStatus, __(':type task: :step', ['type' => $locked->type->label(), 'step' => $step]), $userId);
            }

            return $locked;
        });
    }
}
