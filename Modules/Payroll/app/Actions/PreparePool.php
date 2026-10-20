<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Modules\HR\Contracts\PayrollFacts;
use Modules\HR\DTOs\AttendanceFacts;
use Modules\HR\DTOs\PayrollEmployee;
use Modules\Payroll\Contracts\PoolCollections;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolRule;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\PoolParticipant;
use Modules\Payroll\Models\ServiceChargeAllocation;
use Modules\Payroll\Models\ServiceChargePool;
use Modules\Payroll\Services\Calculation\EmployedDays;
use Modules\Payroll\Services\Calculation\PoolDistributor;
use Modules\Payroll\Services\PoolSettings;
use Modules\Payroll\Services\SalaryPreview;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Works out a month's service charge or tips pool for a property (Step 6.6): what was collected (from the ledger), the share
 * that goes to staff, and each eligible employee's share by the chosen rule. Working it out again replaces the shares, until
 * the pool is finalised.
 *
 * Who shares: employees who worked at the property that month, have a salary (so payroll can pay them) and are eligible. Weight:
 * equal shares and points are scaled by the part of the month employed; days worked is the scheduled days less absences and
 * unpaid leave (paid leave counts as worked).
 */
class PreparePool extends Action
{
    public function __construct(
        private readonly PayrollFacts $facts,
        private readonly PoolCollections $collections,
        private readonly PoolSettings $settings,
        private readonly SalaryPreview $salaries,
        private readonly PoolDistributor $distributor,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @throws PayrollRuleViolated
     */
    public function handle(int $propertyId, string $month, PoolKind $kind, ?PoolRule $rule, ?string $percent, ?int $userId): ServiceChargePool
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new PayrollRuleViolated(__('Choose a month.'));
        }

        $start = CarbonImmutable::parse($month.'-01');
        $end = $start->endOfMonth();
        $today = CarbonImmutable::parse($this->properties->find($propertyId)->businessDate ?? now()->toDateString());

        if ($start->gt($today)) {
            throw new PayrollRuleViolated(__('That month has not begun, so nothing has been collected.'));
        }

        $rule ??= $this->settings->rule($propertyId);
        $percent ??= $this->settings->staffPercent($kind, $propertyId);

        if (! is_numeric($percent) || BigDecimal::of($percent)->isLessThan(0) || BigDecimal::of($percent)->isGreaterThan(100)) {
            throw new PayrollRuleViolated(__('The staff share is between 0 and 100 percent.'));
        }

        $existing = ServiceChargePool::query()->where('property_id', $propertyId)->whereDate('period', $start->toDateString())->where('kind', $kind->value)->first();

        if ($existing instanceof ServiceChargePool && $existing->status !== PoolStatus::Calculated) {
            throw new PayrollRuleViolated(__('The :kind pool for :month is :status, so it cannot be worked out again.', ['kind' => mb_strtolower($kind->label()), 'month' => $start->format('F Y'), 'status' => mb_strtolower($existing->status->label())]));
        }

        $collected = BigDecimal::of($this->collections->collected($propertyId, $start->toDateString(), $end->toDateString(), $kind))->toScale(2, RoundingMode::HalfUp);
        $distributable = $collected->multipliedBy($percent)->dividedBy(100, 2, RoundingMode::HalfUp);
        $weights = $this->weights($propertyId, $month, $start, $end, $today, $rule);

        if ($distributable->isPositive() && $weights['weights'] === []) {
            throw new PayrollRuleViolated(__('No eligible employee has a share this month, so :amount cannot be distributed.', ['amount' => number_format((float) (string) $distributable, 2)]));
        }

        $amounts = $distributable->isPositive() ? $this->distributor->distribute((string) $distributable, $weights['weights']) : [];

        return $this->transaction(function () use ($existing, $propertyId, $start, $kind, $rule, $collected, $percent, $distributable, $weights, $amounts, $userId): ServiceChargePool {
            $data = [
                'rule' => $rule, 'collected' => (string) $collected, 'staff_percent' => (string) BigDecimal::of($percent)->toScale(2), 'distributable' => (string) $distributable->toScale(2), 'employees_count' => count($amounts), 'status' => PoolStatus::Calculated,
            ];

            if ($existing instanceof ServiceChargePool) {
                ServiceChargeAllocation::query()->where('service_charge_pool_id', $existing->id)->delete();
                $existing->forceFill($data)->save();
                $pool = $existing;
            } else {
                $pool = ServiceChargePool::query()->create([...$data, 'property_id' => $propertyId, 'kind' => $kind, 'period' => $start->toDateString(), 'created_by' => $userId]);
            }

            foreach ($amounts as $employeeId => $amount) {
                $employee = $weights['employees'][$employeeId];
                ServiceChargeAllocation::query()->create([
                    'service_charge_pool_id' => $pool->id, 'employee_id' => $employeeId, 'employee_no' => $employee->employeeNo, 'employee_name' => $employee->name, 'department_id' => $employee->departmentId,
                    'weight' => $weights['weights'][$employeeId], 'amount' => $amount,
                ]);
            }

            return $pool;
        });
    }

    /**
     * @return array{weights: array<int, string>, employees: array<int, PayrollEmployee>}
     */
    private function weights(int $propertyId, string $month, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $today, PoolRule $rule): array
    {
        $participants = PoolParticipant::query()->get()->keyBy('employee_id');
        $attendance = $rule === PoolRule::DaysWorked ? $this->facts->attendance($propertyId, $month, min($end->toDateString(), $today->toDateString())) : [];
        $weights = [];
        $employees = [];

        foreach ($this->facts->employees($propertyId, $month) as $employee) {
            $participant = $participants->get($employee->id);

            if ($participant instanceof PoolParticipant && ! $participant->is_eligible) {
                continue;
            }

            $until = $employee->exitedOn !== null && $employee->exitedOn < $end->toDateString() ? $employee->exitedOn : $end->toDateString();

            if (! $this->salaries->salaryOn($employee->id, $until) instanceof EmployeeSalary) {
                continue;
            }

            $fraction = BigDecimal::of(EmployedDays::count($employee->joinedOn, $employee->exitedOn, $start, $end))->dividedBy($start->daysInMonth, 4, RoundingMode::HalfUp);
            $weight = match ($rule) {
                PoolRule::Equal => $fraction,
                PoolRule::Points => BigDecimal::of($participant instanceof PoolParticipant ? (string) $participant->points : '1')->multipliedBy($fraction),
                PoolRule::DaysWorked => $this->daysWorked($attendance[$employee->id] ?? new AttendanceFacts(0, '0.0', '0.0', 0)),
            };

            if ($weight->isPositive()) {
                $weights[$employee->id] = (string) $weight->toScale(4, RoundingMode::HalfUp);
                $employees[$employee->id] = $employee;
            }
        }

        return ['weights' => $weights, 'employees' => $employees];
    }

    private function daysWorked(AttendanceFacts $facts): BigDecimal
    {
        $days = BigDecimal::of($facts->scheduledDays)->minus($facts->absentDays)->minus($facts->unpaidLeaveDays);

        return $days->isPositive() ? $days : BigDecimal::zero();
    }
}
