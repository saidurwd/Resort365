<?php

namespace Modules\Housekeeping\DTOs;

use App\Support\DTOs\Data;
use Modules\Housekeeping\Enums\WorkOrderCategory;
use Modules\Housekeeping\Enums\WorkOrderPriority;

/**
 * A fault to report: in a room, or somewhere described by location.
 */
final readonly class WorkOrderData extends Data
{
    public function __construct(
        public int $propertyId,
        public string $title,
        public WorkOrderCategory $category,
        public WorkOrderPriority $priority,
        public ?int $roomId = null,
        public ?string $location = null,
        public ?string $description = null,
        public ?int $reportedBy = null,
        public ?int $assignedTo = null,
        public ?string $dueOn = null,
        public ?int $scheduleId = null,
    ) {}
}
