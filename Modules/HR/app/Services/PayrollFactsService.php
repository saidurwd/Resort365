<?php

namespace Modules\HR\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\HR\Contracts\PayrollFacts;
use Modules\HR\DTOs\AttendanceFacts;
use Modules\HR\DTOs\BankDetails;
use Modules\HR\DTOs\PayrollEmployee;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Enums\HalfDay;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\Roster;

/**
 * HR's implementation of `PayrollFacts` (Step 6.5).
 */
class PayrollFactsService implements PayrollFacts
{
    public function __construct(private readonly ScheduleLookup $schedule) {}

    public function employees(int $propertyId, string $month): array
    {
        $start = CarbonImmutable::parse($month.'-01');

        return Employee::query()->with('designation')->where('property_id', $propertyId)->whereDate('joined_on', '<=', $start->endOfMonth()->toDateString())
            ->where(fn ($query) => $query->whereNull('exited_on')->orWhereDate('exited_on', '>=', $start->toDateString()))->orderBy('first_name')->orderBy('last_name')->get()
            ->map(fn (Employee $employee): PayrollEmployee => new PayrollEmployee(
                $employee->id, $employee->employee_no, $employee->full_name, $employee->designation->name, $employee->department_id, $employee->joined_on->toDateString(),
                $employee->exited_on?->toDateString(), $employee->email,
            ))->values()->all();
    }

    public function attendance(int $propertyId, string $month, string $upTo): array
    {
        $start = CarbonImmutable::parse($month.'-01');
        $from = $start->toDateString();
        $to = min($start->endOfMonth()->toDateString(), $upTo);
        $ids = Employee::query()->where('property_id', $propertyId)->whereDate('joined_on', '<=', $start->endOfMonth()->toDateString())->pluck('id')->all();
        $facts = [];

        if ($to < $from) {
            return array_fill_keys($ids, new AttendanceFacts(0, '0.0', '0.0', 0));
        }

        $holidays = $this->schedule->holidays($propertyId, $from, $to);
        $scheduled = Roster::query()->whereIn('employee_id', $ids)->whereBetween('work_date', [$from, $to])->whereNotNull('shift_id')->get()->groupBy('employee_id');
        $records = Attendance::query()->whereIn('employee_id', $ids)->whereBetween('work_date', [$from, $to])->get()->groupBy('employee_id');
        $unpaid = LeaveRequest::query()->with('leaveType')->whereIn('employee_id', $ids)->where('status', LeaveStatus::Approved->value)->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)->get()
            ->filter(fn (LeaveRequest $leave): bool => ! $leave->leaveType->is_paid)->groupBy('employee_id');

        foreach ($ids as $id) {
            $days = $scheduled->get($id, collect())->map(fn (Roster $roster): string => $roster->work_date->toDateString())->reject(fn (string $date): bool => isset($holidays[$date]))->values();
            $mine = $records->get($id, collect());
            $byDate = $mine->keyBy(fn (Attendance $record): string => $record->work_date->toDateString());
            $absent = BigDecimal::of((string) $days->filter(fn (string $date): bool => ! isset($byDate[$date]))->count())
                ->plus((string) $mine->where('status', AttendanceStatus::Absent)->count())->plus(BigDecimal::of((string) $mine->where('status', AttendanceStatus::HalfDay)->count())->dividedBy(2, 1));
            $leaveDays = BigDecimal::zero();

            foreach ($unpaid->get($id, collect()) as $leave) {
                $inMonth = count(array_filter($leave->charged_dates, fn (string $date): bool => $date >= $from && $date <= $to));
                $leaveDays = $leaveDays->plus(BigDecimal::of((string) $inMonth)->multipliedBy($leave->half_day instanceof HalfDay ? '0.5' : '1'));
            }

            $facts[$id] = new AttendanceFacts($days->count(), (string) $absent->toScale(1), (string) $leaveDays->toScale(1), (int) $mine->sum('overtime_minutes'));
        }

        return $facts;
    }

    public function bankDetails(int $employeeId): ?BankDetails
    {
        $employee = Employee::query()->find($employeeId);

        return $employee instanceof Employee ? new BankDetails($employee->bank_name, $employee->bank_branch, $employee->bank_account_name, $employee->bank_account_no) : null;
    }
}
