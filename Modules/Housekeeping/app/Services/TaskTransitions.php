<?php

namespace Modules\Housekeeping\Services;

use InvalidArgumentException;
use Modules\Housekeeping\Enums\TaskStatus;
use Modules\Property\Enums\HousekeepingStatus;

/**
 * The cleaning-task workflow (ARCHITECTURE §5.11), without the database: which step may follow
 * which, the task's next status and the room's cleaning status after it.
 *
 * start: Pending → In progress · finish: Pending/In progress → Done, room Clean ·
 * pass: Done → Inspected, room Inspected · fail: Done → Pending, room Dirty ·
 * skip: Pending/In progress → Skipped (e.g. the guest declined service), room unchanged.
 */
class TaskTransitions
{
    public const array STEPS = ['start', 'finish', 'pass', 'fail', 'skip'];

    /**
     * @var array<string, list<TaskStatus>>
     */
    private const array FROM = [
        'start' => [TaskStatus::Pending],
        'finish' => [TaskStatus::Pending, TaskStatus::InProgress],
        'pass' => [TaskStatus::Done],
        'fail' => [TaskStatus::Done],
        'skip' => [TaskStatus::Pending, TaskStatus::InProgress],
    ];

    public function allowed(TaskStatus $from, string $step): bool
    {
        return in_array($from, self::FROM[$step] ?? [], true);
    }

    public function next(string $step): TaskStatus
    {
        return match ($step) {
            'start' => TaskStatus::InProgress,
            'finish' => TaskStatus::Done,
            'pass' => TaskStatus::Inspected,
            'fail' => TaskStatus::Pending,
            'skip' => TaskStatus::Skipped,
            default => throw new InvalidArgumentException("Unknown step {$step}."),
        };
    }

    /**
     * The room's cleaning status after the step, or null when it does not change.
     */
    public function roomStatus(string $step): ?HousekeepingStatus
    {
        return match ($step) {
            'finish' => HousekeepingStatus::Clean,
            'pass' => HousekeepingStatus::Inspected,
            'fail' => HousekeepingStatus::Dirty,
            default => null,
        };
    }

    /**
     * The steps a task in this status can take now.
     *
     * @return list<string>
     */
    public function available(TaskStatus $from): array
    {
        return array_values(array_filter(self::STEPS, fn (string $step): bool => $this->allowed($from, $step)));
    }
}
