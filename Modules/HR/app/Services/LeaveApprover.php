<?php

namespace Modules\HR\Services;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\Roster;

/**
 * Carries out the decisions on a leave request (Step 6.3): approving moves the days from pending to used and puts the leave
 * on the roster in place of the shifts; rejecting or cancelling gives the days back, and cancelling an approved leave
 * puts the shifts back. These are system reactions, so they work across the tenant's properties whoever decides.
 */
class LeaveApprover
{
    public function __construct(
        private readonly PropertyContext $properties,
        private readonly TenantContext $tenant,
    ) {}

    public function approve(LeaveRequest $request): void
    {
        $this->properties->unrestricted(function () use ($request): void {
            $locked = LeaveRequest::query()->lockForUpdate()->with('leaveType')->find($request->id);

            if (! $locked instanceof LeaveRequest || $locked->status !== LeaveStatus::Pending) {
                return;
            }

            if ($locked->leaveType->has_balance) {
                $this->move($locked, pending: false, used: true);
            }

            $this->putOnRoster($locked);
            $locked->forceFill(['status' => LeaveStatus::Approved, 'decided_at' => now()])->save();
        });
    }

    public function reject(LeaveRequest $request): void
    {
        $this->properties->unrestricted(function () use ($request): void {
            $locked = LeaveRequest::query()->lockForUpdate()->with('leaveType')->find($request->id);

            if (! $locked instanceof LeaveRequest || $locked->status !== LeaveStatus::Pending) {
                return;
            }

            if ($locked->leaveType->has_balance) {
                $this->move($locked, pending: false, used: false);
            }

            $locked->forceFill(['status' => LeaveStatus::Rejected, 'decided_at' => now()])->save();
        });
    }

    /**
     * Withdraws a pending request or cancels an approved one that has not started.
     */
    public function cancel(LeaveRequest $request): void
    {
        $this->properties->unrestricted(function () use ($request): void {
            $locked = LeaveRequest::query()->lockForUpdate()->with('leaveType')->find($request->id);

            if (! $locked instanceof LeaveRequest || ! in_array($locked->status, [LeaveStatus::Pending, LeaveStatus::Approved], true)) {
                return;
            }

            if ($locked->leaveType->has_balance) {
                $locked->status === LeaveStatus::Pending ? $this->move($locked, pending: false, used: false) : $this->restoreUsed($locked);
            }

            if ($locked->status === LeaveStatus::Approved) {
                $this->takeOffRoster($locked);
            }

            $locked->forceFill(['status' => LeaveStatus::Cancelled, 'cancelled_at' => now()])->save();
        });
    }

    /** Reserves the days of a new request on the balances. */
    public function reserve(LeaveRequest $request): void
    {
        foreach ($request->days_by_year as $year => $days) {
            $balance = LeaveBalance::query()->lockForUpdate()->where(['employee_id' => $request->employee_id, 'leave_type_id' => $request->leave_type_id, 'year' => (int) $year])->first();

            if ($balance instanceof LeaveBalance) {
                $balance->forceFill(['pending' => (string) BigDecimal::of((string) $balance->pending)->plus($days)->toScale(1, RoundingMode::HalfUp)])->save();
            }
        }
    }

    /** Takes the days off pending, and puts them on used when `$used`. */
    private function move(LeaveRequest $request, bool $pending, bool $used): void
    {
        foreach ($request->days_by_year as $year => $days) {
            $balance = LeaveBalance::query()->lockForUpdate()->where(['employee_id' => $request->employee_id, 'leave_type_id' => $request->leave_type_id, 'year' => (int) $year])->first();

            if ($balance instanceof LeaveBalance) {
                $balance->forceFill([
                    'pending' => (string) BigDecimal::of((string) $balance->pending)->minus($days)->toScale(1, RoundingMode::HalfUp),
                    'used' => $used ? (string) BigDecimal::of((string) $balance->used)->plus($days)->toScale(1, RoundingMode::HalfUp) : $balance->used,
                ])->save();
            }
        }
    }

    private function restoreUsed(LeaveRequest $request): void
    {
        foreach ($request->days_by_year as $year => $days) {
            $balance = LeaveBalance::query()->lockForUpdate()->where(['employee_id' => $request->employee_id, 'leave_type_id' => $request->leave_type_id, 'year' => (int) $year])->first();

            if ($balance instanceof LeaveBalance) {
                $balance->forceFill(['used' => (string) BigDecimal::of((string) $balance->used)->minus($days)->toScale(1, RoundingMode::HalfUp)])->save();
            }
        }
    }

    private function putOnRoster(LeaveRequest $request): void
    {
        $tenantId = $this->tenant->tenantOrFail(self::class)->id;
        $existing = Roster::query()->where('employee_id', $request->employee_id)->whereIn('work_date', $request->charged_dates)->get()->keyBy(fn (Roster $roster): string => $roster->work_date->toDateString());
        $now = now();
        $rows = [];

        foreach ($request->charged_dates as $date) {
            $row = $existing[$date] ?? null;
            $rows[] = [
                'tenant_id' => $tenantId, 'property_id' => $row->property_id ?? $request->property_id, 'employee_id' => $request->employee_id, 'work_date' => $date, 'shift_id' => null, 'leave_request_id' => $request->id,
                'replaced_shift_id' => $row !== null && $row->leave_request_id === null ? $row->shift_id : ($row->replaced_shift_id ?? null), 'leave_created' => $row === null || $row->leave_created, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Roster::query()->upsert($chunk, ['employee_id', 'work_date'], ['shift_id', 'leave_request_id', 'replaced_shift_id', 'leave_created', 'updated_at']);
        }
    }

    private function takeOffRoster(LeaveRequest $request): void
    {
        foreach (Roster::query()->where('leave_request_id', $request->id)->get() as $roster) {
            if ($roster->leave_created) {
                $roster->delete();
            } else {
                $roster->forceFill(['shift_id' => $roster->replaced_shift_id, 'leave_request_id' => null, 'replaced_shift_id' => null])->save();
            }
        }
    }
}
