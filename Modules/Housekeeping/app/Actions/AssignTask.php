<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\Enums\TaskStatus;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Models\HousekeepingTask;
use Modules\IAM\Contracts\UserDirectory;

/**
 * Gives cleaning tasks to an attendant (a user who may perform tasks), or takes them back (null).
 */
class AssignTask extends Action
{
    public function __construct(
        private readonly UserDirectory $users,
    ) {}

    /**
     * @param  list<int>  $taskIds
     * @return int how many tasks were assigned
     *
     * @throws HousekeepingNotPossible
     */
    public function handle(array $taskIds, ?int $attendantId): int
    {
        if ($attendantId !== null && ! $this->users->userCan($attendantId, 'housekeeping.task.perform')) {
            throw new HousekeepingNotPossible(__('That person cannot be given cleaning tasks.'));
        }

        return HousekeepingTask::query()->whereIn('id', $taskIds)->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])->get()
            ->each(fn (HousekeepingTask $task): bool => $task->forceFill(['assigned_to' => $attendantId])->save())->count();
    }
}
