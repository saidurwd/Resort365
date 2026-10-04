<?php

/*
| TaskTransitions: the cleaning-task workflow and the room status after each step.
*/

use Modules\Housekeeping\Enums\TaskStatus;
use Modules\Housekeeping\Services\TaskTransitions;
use Modules\Property\Enums\HousekeepingStatus;

it('allows each step only from the right status', function (TaskStatus $from, array $steps): void {
    expect((new TaskTransitions)->available($from))->toBe($steps);
})->with([
    'pending' => [TaskStatus::Pending, ['start', 'finish', 'skip']],
    'in progress' => [TaskStatus::InProgress, ['finish', 'skip']],
    'done' => [TaskStatus::Done, ['pass', 'fail']],
    'inspected' => [TaskStatus::Inspected, []],
    'skipped' => [TaskStatus::Skipped, []],
]);

it('moves the task and the room on', function (string $step, TaskStatus $task, ?HousekeepingStatus $room): void {
    $transitions = new TaskTransitions;

    expect($transitions->next($step))->toBe($task)->and($transitions->roomStatus($step))->toBe($room);
})->with([
    'start' => ['start', TaskStatus::InProgress, null],
    'finish' => ['finish', TaskStatus::Done, HousekeepingStatus::Clean],
    'pass' => ['pass', TaskStatus::Inspected, HousekeepingStatus::Inspected],
    'fail' => ['fail', TaskStatus::Pending, HousekeepingStatus::Dirty],
    'skip' => ['skip', TaskStatus::Skipped, null],
]);

it('refuses unknown steps', function (): void {
    (new TaskTransitions)->next('mop');
})->throws(InvalidArgumentException::class);
