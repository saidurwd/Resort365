<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Modules\HR\Contracts\PayrollFacts;
use Modules\HR\DTOs\AttendanceFacts;
use Modules\HR\DTOs\PayrollEmployee;
use Modules\Payroll\DTOs\PayrollRules;
use Modules\Payroll\DTOs\PayslipData;
use Modules\Payroll\DTOs\PayslipInput;
use Modules\Payroll\DTOs\PayslipLineData;
use Modules\Payroll\DTOs\TaxTableData;
use Modules\Payroll\Enums\InstallmentStatus;
use Modules\Payroll\Enums\LoanKind;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\LoanInstallment;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;
use Modules\Payroll\Models\TaxTable;
use Modules\Payroll\Services\Calculation\PayslipBuilder;
use Modules\Payroll\Services\PayrollSettings;
use Modules\Payroll\Services\RunTotals;
use Modules\Payroll\Services\SalaryPreview;
use Modules\Payroll\Services\StructureLoader;
use Modules\Payroll\Services\TaxTableResolver;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Calculates a payroll run (Step 6.5): a payslip for every employee who worked at the property that month and has a salary,
 * from their salary structure, the month's attendance (overtime, absence, unpaid leave), the loan and advance installments
 * due, the part of the month they worked, and the annual components the run includes. Calculating again replaces the
 * payslips (adjustments included), until the run goes for approval. Employees with no salary are listed as warnings.
 */
class CalculatePayroll extends Action
{
    public function __construct(
        private readonly PayrollFacts $facts,
        private readonly SalaryPreview $salaries,
        private readonly StructureLoader $structures,
        private readonly TaxTableResolver $taxTables,
        private readonly PayslipBuilder $builder,
        private readonly PayrollSettings $settings,
        private readonly RunTotals $totals,
        private readonly DepartmentDirectory $departments,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @throws PayrollRuleViolated
     */
    public function handle(PayrollRun $run): PayrollRun
    {
        if (! $run->isOpen()) {
            throw new PayrollRuleViolated(__('Payroll run :no is :status, so it cannot be calculated again.', ['no' => $run->run_no, 'status' => mb_strtolower($run->status->label())]));
        }

        $start = $run->period->toImmutable()->startOfMonth();
        $end = $start->endOfMonth();
        $month = $start->format('Y-m');
        $employees = $this->facts->employees($run->property_id, $month);
        $today = CarbonImmutable::parse($this->properties->find($run->property_id)->businessDate ?? now()->toDateString());
        $attendance = $this->facts->attendance($run->property_id, $month, min($end->toDateString(), $today->toDateString()));
        $rules = $this->settings->rules($run->property_id);
        $names = collect($this->departments->all(false))->pluck('name', 'id');
        $table = $this->taxTables->forDate($end->toDateString());
        $tax = $table instanceof TaxTable ? $this->taxTables->data($table) : null;
        $due = $this->dueInstallments(array_map(fn (PayrollEmployee $employee): int => $employee->id, $employees), $start);

        return $this->transaction(function () use ($run, $start, $end, $employees, $attendance, $rules, $names, $tax, $due): PayrollRun {
            PayslipLine::query()->whereIn('payslip_id', Payslip::query()->where('payroll_run_id', $run->id)->pluck('id'))->delete();
            Payslip::query()->where('payroll_run_id', $run->id)->delete();
            $warnings = [];

            foreach ($employees as $employee) {
                $until = $employee->exitedOn !== null && $employee->exitedOn < $end->toDateString() ? $employee->exitedOn : $end->toDateString();
                $salary = $this->salaries->salaryOn($employee->id, $until);

                if (! $salary instanceof EmployeeSalary) {
                    $warnings[] = __(':name (:no) has no salary, so there is no payslip.', ['name' => $employee->name, 'no' => $employee->employeeNo]);

                    continue;
                }

                $data = $this->builder->build($this->input($salary, $employee, $attendance[$employee->id] ?? new AttendanceFacts(0, '0.0', '0.0', 0), $due[$employee->id] ?? ['loan' => '0', 'advance' => '0', 'ids' => []], $run, $start, $end, $tax, $rules));
                $this->store($run, $salary, $employee, $names[$employee->departmentId] ?? null, $data, $attendance[$employee->id] ?? null, $start, $end, $due[$employee->id]['ids'] ?? []);
            }

            $run->forceFill(['status' => RunStatus::Calculated, 'calculated_at' => now(), 'warnings' => $warnings === [] ? null : $warnings])->save();

            return $this->totals->refreshRun($run);
        });
    }

    /**
     * @param  array{loan: string, advance: string, ids: list<int>}  $due
     */
    private function input(EmployeeSalary $salary, PayrollEmployee $employee, AttendanceFacts $facts, array $due, PayrollRun $run, CarbonImmutable $start, CarbonImmutable $end, ?TaxTableData $tax, PayrollRules $rules): PayslipInput
    {
        $employed = $this->employedDays($employee, $start, $end);

        return new PayslipInput(
            $this->structures->lines($salary->structure), (string) $salary->basic, $tax, $salary->tax_category, $salary->tax_area, $salary->amounts ?? [], $run->include_annual ?? [],
            (string) BigDecimal::of($employed)->dividedBy($start->daysInMonth, 4, RoundingMode::HalfUp), $facts->absentDays, $facts->unpaidLeaveDays, $facts->overtimeMinutes, $due['loan'], $due['advance'], $rules,
        );
    }

    /**
     * @param  list<int>  $installmentIds
     */
    private function store(PayrollRun $run, EmployeeSalary $salary, PayrollEmployee $employee, ?string $department, PayslipData $data, ?AttendanceFacts $facts, CarbonImmutable $start, CarbonImmutable $end, array $installmentIds): void
    {
        $employed = $this->employedDays($employee, $start, $end);
        $slip = Payslip::query()->create([
            'property_id' => $run->property_id, 'payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'employee_no' => $employee->employeeNo, 'employee_name' => $employee->name, 'employee_email' => $employee->email,
            'designation' => $employee->designation, 'department_id' => $employee->departmentId, 'department_name' => $department, 'structure_name' => $salary->structure->name, 'basic' => (string) $salary->basic,
            'days_in_month' => $start->daysInMonth, 'days_employed' => $employed, 'pay_fraction' => (string) BigDecimal::of($employed)->dividedBy($start->daysInMonth, 4, RoundingMode::HalfUp),
            'absent_days' => $facts->absentDays ?? '0.0', 'unpaid_leave_days' => $facts->unpaidLeaveDays ?? '0.0', 'overtime_minutes' => $facts->overtimeMinutes ?? 0, 'gross' => $data->gross, 'deductions' => $data->deductions,
            'tax' => $data->tax, 'net' => $data->net, 'employer_cost' => $data->employerCost, 'installment_ids' => $installmentIds === [] ? null : $installmentIds,
        ]);

        foreach ($data->lines as $line) {
            /** @var PayslipLineData $line */
            PayslipLine::query()->create(['payslip_id' => $slip->id, 'code' => $line->code, 'name' => $line->name, 'kind' => $line->kind, 'is_taxable' => $line->taxable, 'amount' => $line->amount, 'source' => $line->source, 'sort_order' => $line->sortOrder]);
        }

        $slip->lines()->create(['code' => 'TAX', 'name' => __('Income tax'), 'kind' => 'deduction', 'is_taxable' => false, 'amount' => $data->tax, 'source' => 'structure', 'sort_order' => 990]);
    }

    /** The days of the month the employee was employed (joined or left part-way through). */
    private function employedDays(PayrollEmployee $employee, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $from = max($employee->joinedOn, $start->toDateString());
        $to = $employee->exitedOn !== null && $employee->exitedOn < $end->toDateString() ? $employee->exitedOn : $end->toDateString();

        return max(0, (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) + 1);
    }

    /**
     * The installments of loans and advances due by the month, per employee: the sums to deduct and the installment ids.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, array{loan: string, advance: string, ids: list<int>}>
     */
    private function dueInstallments(array $employeeIds, CarbonImmutable $start): array
    {
        $due = [];

        foreach (LoanInstallment::query()->with('loan')->where('status', InstallmentStatus::Scheduled->value)->whereDate('due_month', '<=', $start->toDateString())
            ->whereHas('loan', fn ($query) => $query->whereIn('employee_id', $employeeIds))->orderBy('due_month')->get() as $installment) {
            $employeeId = $installment->loan->employee_id;
            $due[$employeeId] ??= ['loan' => '0', 'advance' => '0', 'ids' => []];
            $key = $installment->loan->kind === LoanKind::Advance ? 'advance' : 'loan';
            $due[$employeeId][$key] = (string) BigDecimal::of($due[$employeeId][$key])->plus((string) $installment->amount);
            $due[$employeeId]['ids'][] = $installment->id;
        }

        return $due;
    }
}
