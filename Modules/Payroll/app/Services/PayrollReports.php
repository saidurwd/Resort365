<?php

namespace Modules\Payroll\Services;

use Brick\Math\BigDecimal;
use Modules\HR\Contracts\PayrollFacts;
use Modules\HR\DTOs\BankDetails;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;

/**
 * The payroll register and the bank transfer file of a run (Step 6.5), as the array the report table and CSV download use.
 */
class PayrollReports
{
    public function __construct(private readonly PayrollFacts $facts) {}

    /**
     * Every payslip's gross, deductions, tax, net and employer cost, then the same by department.
     *
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function register(PayrollRun $run): array
    {
        $slips = $run->payslips()->get();
        $money = fn (string|BigDecimal $value): string => number_format((float) (string) $value, 2);
        $rows = [];

        foreach ($slips as $slip) {
            $rows[] = [$slip->employee_no.' · '.$slip->employee_name, $slip->department_name ?? '—', $money((string) $slip->gross), $money((string) $slip->deductions), $money((string) $slip->tax), $money((string) $slip->net), $money((string) $slip->employer_cost)];
        }

        return [
            'title' => __('Payroll register :no', ['no' => $run->run_no]), 'columns' => [__('Employee'), __('Department'), __('Gross'), __('Deductions'), __('Income tax'), __('Net pay'), __('Employer cost')], 'rows' => $rows,
            'totals' => [__('Total'), '', $money((string) $run->gross_total), $money((string) $run->deductions_total), $money((string) $run->tax_total), $money((string) $run->net_total), $money((string) $run->employer_total)],
            'summary' => [['label' => __('Employees'), 'value' => (string) $run->employees_count], ['label' => __('Gross'), 'value' => $money((string) $run->gross_total)], ['label' => __('Income tax'), 'value' => $money((string) $run->tax_total)], ['label' => __('Net pay'), 'value' => $money((string) $run->net_total)]],
            'note' => __('Gross is the earnings paid this month; deductions are everything except income tax (provident fund, absence, loans); net pay = gross − deductions − income tax.'),
        ];
    }

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function byDepartment(PayrollRun $run): array
    {
        $money = fn (string|BigDecimal $value): string => number_format((float) (string) $value, 2);
        $rows = [];

        foreach ($run->payslips()->get()->groupBy(fn (Payslip $slip): string => $slip->department_name ?? '—')->sortKeys() as $department => $slips) {
            $sum = fn (string $column): string => (string) $slips->reduce(fn (BigDecimal $carry, Payslip $slip): BigDecimal => $carry->plus((string) $slip->{$column}), BigDecimal::zero());
            $rows[] = [(string) $department, (string) $slips->count(), $money($sum('gross')), $money($sum('deductions')), $money($sum('tax')), $money($sum('net')), $money($sum('employer_cost'))];
        }

        return [
            'title' => __('By department'), 'columns' => [__('Department'), __('Employees'), __('Gross'), __('Deductions'), __('Income tax'), __('Net pay'), __('Employer cost')], 'rows' => $rows,
            'totals' => [__('Total'), (string) $run->employees_count, $money((string) $run->gross_total), $money((string) $run->deductions_total), $money((string) $run->tax_total), $money((string) $run->net_total), $money((string) $run->employer_total)],
            'summary' => [], 'note' => null,
        ];
    }

    /**
     * The salary transfer file: each employee's bank and account and the net pay. Employees without an account number are
     * listed in `missing`.
     *
     * @return array{data: array{columns: list<string>, rows: list<list<string>>, totals: list<string>|null}, missing: list<string>}
     */
    public function bankFile(PayrollRun $run): array
    {
        $rows = [];
        $missing = [];

        foreach ($run->payslips()->get() as $slip) {
            $bank = $this->facts->bankDetails($slip->employee_id);

            if (! $bank instanceof BankDetails || $bank->accountNumber === null || $bank->accountNumber === '') {
                $missing[] = $slip->employee_name;
            }

            $rows[] = [$slip->employee_no, $slip->employee_name, $bank->bank ?? '', $bank->branch ?? '', $bank->accountName ?? $slip->employee_name, $bank->accountNumber ?? '', (string) $slip->net, $run->run_no.' '.$run->period->format('M Y')];
        }

        return ['data' => ['columns' => ['Employee no', 'Name', 'Bank', 'Branch', 'Account name', 'Account number', 'Net amount', 'Reference'], 'rows' => $rows, 'totals' => null], 'missing' => $missing];
    }
}
