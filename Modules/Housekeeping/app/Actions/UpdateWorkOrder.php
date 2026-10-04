<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Housekeeping\Enums\WorkOrderPriority;
use Modules\Housekeeping\Enums\WorkOrderStatus;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Models\MaintenanceRequest;
use Modules\IAM\Contracts\UserDirectory;

/**
 * Works a maintenance order: priority, technician, status (Open → In progress → On hold → Done, or
 * Cancelled), labour and parts cost and what was done. A done or cancelled order is closed and
 * does not change again; closing it as done needs a note of what was done.
 */
class UpdateWorkOrder extends Action
{
    public function __construct(
        private readonly UserDirectory $users,
    ) {}

    /**
     * @throws HousekeepingNotPossible
     */
    public function handle(MaintenanceRequest $order, WorkOrderStatus $status, WorkOrderPriority $priority, ?int $assignedTo, string $labourCost,
        string $partsCost, ?string $resolution): MaintenanceRequest
    {
        return $this->transaction(function () use ($order, $status, $priority, $assignedTo, $labourCost, $partsCost, $resolution): MaintenanceRequest {
            $locked = MaintenanceRequest::query()->lockForUpdate()->findOrFail($order->id);

            if (in_array($locked->status, [WorkOrderStatus::Done, WorkOrderStatus::Cancelled], true)) {
                throw new HousekeepingNotPossible(__('This work order is closed.'));
            }

            if ($assignedTo !== null && $assignedTo !== $locked->assigned_to && ! $this->users->userCan($assignedTo, 'housekeeping.work-order.view')) {
                throw new HousekeepingNotPossible(__('That person does not work on work orders.'));
            }

            $resolution = trim((string) $resolution);

            if ($status === WorkOrderStatus::Done && $resolution === '') {
                throw new HousekeepingNotPossible(__('Say what was done before closing the work order.'));
            }

            $locked->forceFill([
                'status' => $status, 'priority' => $priority, 'assigned_to' => $assignedTo,
                'labour_cost' => (string) BigDecimal::of($labourCost)->toScale(2), 'parts_cost' => (string) BigDecimal::of($partsCost)->toScale(2),
                'resolution' => $resolution !== '' ? mb_substr($resolution, 0, 1000) : null,
                'started_at' => $locked->started_at ?? (in_array($status, [WorkOrderStatus::InProgress, WorkOrderStatus::Done], true) ? now() : null),
                'completed_at' => in_array($status, [WorkOrderStatus::Done, WorkOrderStatus::Cancelled], true) ? now() : null,
            ])->save();

            return $locked;
        });
    }
}
