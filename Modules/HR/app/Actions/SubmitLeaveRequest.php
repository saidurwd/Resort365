<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\DTOs\ApprovalSummary;
use Modules\HR\Enums\HalfDay;
use Modules\HR\Enums\LeaveStatus;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Employee;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeavePolicy;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Services\LeaveApprover;
use Modules\HR\Services\LeaveDayCounter;
use Modules\HR\Services\ScheduleLookup;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Asks for leave for an employee (Step 6.3), by the employee through their linked user or by HR for anyone. The days are
 * counted from the roster and the holidays (a day off or a holiday is not charged unless the type counts them), and the
 * request is refused if the dates overlap other leave, the employee does not qualify (gender, employment type, months of
 * service, the longest single request) or the balance of any year it touches is too small. Leave can be asked for up to
 * 31 days after the fact (sick leave). The days are held on the balance until the `leave_request` approval is decided; a
 * workflow that needs no approval for the length approves it at once.
 */
class SubmitLeaveRequest extends Action
{
    private const int LATEST_DAYS_AFTER = 31;

    private const int LONGEST_REQUEST_DAYS = 200;

    public function __construct(
        private readonly ScheduleLookup $schedule,
        private readonly DocumentNumbers $numbers,
        private readonly ApprovalEngine $approvals,
        private readonly LeaveApprover $approver,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @throws HrRuleViolated
     */
    public function handle(Employee $employee, LeaveType $type, string $from, string $to, ?HalfDay $half, ?string $reason, int $userId): LeaveRequest
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $this->checkDates($employee, $type, $start, $end, $half);
        $policy = LeavePolicy::query()->where('leave_type_id', $type->id)->first();
        $this->checkEligibility($employee, $type, $policy, $start);

        $rosters = $this->schedule->rosters([$employee->id], $from, $to)[$employee->id] ?? [];
        $notWorked = array_map(fn (): true => true, $this->schedule->holidays($employee->property_id, $from, $to));

        foreach ($rosters as $date => $shift) {
            if ($shift === null) {
                $notWorked[$date] = true;
            }
        }

        $count = LeaveDayCounter::count($from, $to, $half instanceof HalfDay, $type->counts_non_working_days, $notWorked);

        if ($count['dates'] === []) {
            throw new HrRuleViolated(__('Every one of those dates is a holiday or a day off, so there is nothing to take leave for.'));
        }

        if ($policy instanceof LeavePolicy && $policy->max_consecutive_days !== null && BigDecimal::of($count['days'])->isGreaterThan($policy->max_consecutive_days)) {
            throw new HrRuleViolated(__(':type can be taken for at most :days days at a time.', ['type' => $type->name, 'days' => $policy->max_consecutive_days]));
        }

        return $this->transaction(function () use ($employee, $type, $from, $to, $half, $reason, $userId, $count): LeaveRequest {
            if (LeaveRequest::query()->where('employee_id', $employee->id)->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from)->exists()) {
                throw new HrRuleViolated(__(':name already has leave that overlaps those dates.', ['name' => $employee->full_name]));
            }

            if ($type->has_balance) {
                foreach ($count['byYear'] as $year => $days) {
                    $balance = LeaveBalance::query()->lockForUpdate()->where(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => (int) $year])->first();

                    if (! $balance instanceof LeaveBalance) {
                        throw new HrRuleViolated(__(':name has no :type balance for :year.', ['name' => $employee->full_name, 'type' => $type->name, 'year' => $year]));
                    }

                    if (BigDecimal::of($balance->available())->isLessThan($days)) {
                        throw new HrRuleViolated(__(':name has only :left days of :type left in :year, and the request needs :days.', ['name' => $employee->full_name, 'left' => $balance->available(), 'type' => $type->name, 'year' => $year, 'days' => $days]));
                    }
                }
            }

            $request = LeaveRequest::query()->create([
                'property_id' => $employee->property_id, 'request_no' => $this->numbers->next('leave_request', $employee->property_id), 'employee_id' => $employee->id, 'leave_type_id' => $type->id,
                'from_date' => $from, 'to_date' => $to, 'days' => $count['days'], 'half_day' => $half, 'charged_dates' => $count['dates'], 'days_by_year' => $count['byYear'],
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null, 'status' => LeaveStatus::Pending, 'requested_by' => $userId,
            ]);

            if ($type->has_balance) {
                $this->approver->reserve($request);
            }

            $approval = $this->approvals->submit('leave_request', $count['days'], __('Leave :no for :name', ['no' => $request->request_no, 'name' => $employee->full_name]), $userId,
                ['leave_request_id' => $request->id], $employee->property_id, 'leave_request', $request->id);

            if ($approval instanceof ApprovalSummary) {
                $request->forceFill(['approval_request_id' => $approval->id])->save();
            } else {
                $this->approver->approve($request);
                $request->refresh();
            }

            return $request;
        }, attempts: 3);
    }

    /**
     * @throws HrRuleViolated
     */
    private function checkDates(Employee $employee, LeaveType $type, CarbonImmutable $start, CarbonImmutable $end, ?HalfDay $half): void
    {
        $today = CarbonImmutable::parse($this->properties->find($employee->property_id)->businessDate ?? now()->toDateString());

        if ($employee->isExited()) {
            throw new HrRuleViolated(__(':name has left.', ['name' => $employee->full_name]));
        }

        if (! $type->is_active) {
            throw new HrRuleViolated(__('":name" is not in use.', ['name' => $type->name]));
        }

        if ($end->lt($start)) {
            throw new HrRuleViolated(__('The last day is before the first.'));
        }

        if ($half instanceof HalfDay && ! $start->eq($end)) {
            throw new HrRuleViolated(__('A half day is one date.'));
        }

        if ($start->lt($employee->joined_on->toDateString())) {
            throw new HrRuleViolated(__(':name joined on :date.', ['name' => $employee->full_name, 'date' => $employee->joined_on->format('d M Y')]));
        }

        if ($start->lt($today->subDays(self::LATEST_DAYS_AFTER))) {
            throw new HrRuleViolated(__('Leave can be asked for up to :days days after it was taken.', ['days' => self::LATEST_DAYS_AFTER]));
        }

        if ($start->diffInDays($end) >= self::LONGEST_REQUEST_DAYS) {
            throw new HrRuleViolated(__('A request can cover at most :days days.', ['days' => self::LONGEST_REQUEST_DAYS]));
        }
    }

    /**
     * @throws HrRuleViolated
     */
    private function checkEligibility(Employee $employee, LeaveType $type, ?LeavePolicy $policy, CarbonImmutable $start): void
    {
        if ($type->gender !== null && $employee->gender !== $type->gender) {
            throw new HrRuleViolated(__(':type is for :gender employees.', ['type' => $type->name, 'gender' => mb_strtolower($type->gender->label())]));
        }

        if (! $policy instanceof LeavePolicy) {
            return;
        }

        if ($policy->employment_types !== null && ! in_array($employee->employment_type->value, $policy->employment_types, true)) {
            throw new HrRuleViolated(__(':type does not apply to :kind employees.', ['type' => $type->name, 'kind' => mb_strtolower($employee->employment_type->label())]));
        }

        if ($policy->min_service_months > 0 && CarbonImmutable::parse($employee->joined_on->toDateString())->addMonthsNoOverflow($policy->min_service_months)->gt($start)) {
            throw new HrRuleViolated(__(':type needs :months months of service.', ['type' => $type->name, 'months' => $policy->min_service_months]));
        }
    }
}
