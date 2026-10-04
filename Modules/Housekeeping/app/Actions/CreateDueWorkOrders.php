<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Housekeeping\DTOs\WorkOrderData;
use Modules\Housekeeping\Enums\WorkOrderPriority;
use Modules\Housekeeping\Models\MaintenanceSchedule;
use Modules\Housekeeping\Services\PreventiveCalendar;

/**
 * Preventive maintenance (ARCHITECTURE §5.11): each active schedule of the property due on or before
 * $today opens a work order (due on the schedule's date) and moves to its next date. One work order
 * per schedule and due date, so running it twice does nothing more.
 *
 * @return int how many work orders were opened
 */
class CreateDueWorkOrders extends Action
{
    public function __construct(
        private readonly ReportWorkOrder $report,
        private readonly PreventiveCalendar $calendar,
    ) {}

    public function handle(int $propertyId, string $today): int
    {
        $opened = 0;
        $due = MaintenanceSchedule::query()->where('property_id', $propertyId)->where('is_active', true)->where('next_due_on', '<=', $today)->orderBy('next_due_on')->get();

        foreach ($due as $schedule) {
            $opened += $this->transaction(function () use ($schedule, $today): int {
                $locked = MaintenanceSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
                $dueOn = $locked->next_due_on->toDateString();

                if (! $this->calendar->isDue($dueOn, $today)) {
                    return 0;
                }

                $locked->forceFill(['next_due_on' => $this->calendar->following($dueOn, $locked->interval_days, $today)])->save();

                try {
                    $this->report->handle(new WorkOrderData($locked->property_id, $locked->title, $locked->category, WorkOrderPriority::Normal, $locked->room_id,
                        $locked->location ?? __('Preventive maintenance'), $locked->notes, null, $locked->assigned_to, $dueOn, $locked->id));
                } catch (UniqueConstraintViolationException) {
                    return 0;
                }

                return 1;
            });
        }

        return $opened;
    }
}
