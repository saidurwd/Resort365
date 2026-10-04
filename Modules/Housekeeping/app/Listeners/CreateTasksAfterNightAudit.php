<?php

namespace Modules\Housekeeping\Listeners;

use Modules\FrontOffice\Events\NightAuditCompleted;
use Modules\Housekeeping\Actions\CreateDailyTasks;

/**
 * After the night audit the new business date starts the daily round: rooms in house get a
 * stayover task and become Dirty.
 */
class CreateTasksAfterNightAudit
{
    public function __construct(
        private readonly CreateDailyTasks $tasks,
    ) {}

    public function handle(NightAuditCompleted $event): void
    {
        $this->tasks->handle($event->propertyId, $event->nextBusinessDate);
    }
}
