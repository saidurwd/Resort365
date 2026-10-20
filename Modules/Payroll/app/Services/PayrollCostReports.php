<?php

namespace Modules\Payroll\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\ServiceChargePool;

/**
 * Payroll reports (Step 6.6): what payroll cost by department over a range of months, the festival bonus register, and the
 * service charge and tips pools. Only approved and paid runs count. Each answers the array the report table and CSV download use.
 */
class PayrollCostReports
{
    /**
     * Gross pay, deductions, tax, net pay and employer cost by department, for the months from `$from` to `$to` ("Y-m").
     * The cost of payroll is the gross pay plus what the employer adds on top.
     *
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function costByDepartment(int $propertyId, string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from.'-01');
        $end = CarbonImmutable::parse($to.'-01')->endOfMonth();
        $money = fn (BigDecimal $value): string => number_format((float) (string) $value, 2);
        $sum = fn ($slips, string $column): BigDecimal => $slips->reduce(fn (BigDecimal $carry, Payslip $slip): BigDecimal => $carry->plus((string) $slip->{$column}), BigDecimal::zero());
        $payslips = Payslip::query()->where('property_id', $propertyId)->whereHas('run', fn ($query) => $query->whereIn('status', [RunStatus::Approved->value, RunStatus::Paid->value])->whereBetween('period', [$start->toDateString(), $end->toDateString()]))->get();
        $rows = [];

        foreach ($payslips->groupBy(fn (Payslip $slip): string => $slip->department_name ?? '—')->sortKeys() as $department => $slips) {
            $gross = $sum($slips, 'gross');
            $employer = $sum($slips, 'employer_cost');
            $rows[] = [(string) $department, (string) $slips->pluck('employee_id')->unique()->count(), $money($gross), $money($sum($slips, 'deductions')), $money($sum($slips, 'tax')), $money($sum($slips, 'net')), $money($employer), $money($gross->plus($employer))];
        }

        $gross = $sum($payslips, 'gross');
        $employer = $sum($payslips, 'employer_cost');

        return [
            'title' => __('Payroll cost :from to :to', ['from' => $start->format('M Y'), 'to' => $end->format('M Y')]),
            'columns' => [__('Department'), __('Employees'), __('Gross'), __('Deductions'), __('Income tax'), __('Net pay'), __('Employer cost'), __('Total cost')],
            'rows' => $rows,
            'totals' => [__('Total'), (string) $payslips->pluck('employee_id')->unique()->count(), $money($gross), $money($sum($payslips, 'deductions')), $money($sum($payslips, 'tax')), $money($sum($payslips, 'net')), $money($employer), $money($gross->plus($employer))],
            'summary' => [['label' => __('Gross'), 'value' => $money($gross)], ['label' => __('Employer cost'), 'value' => $money($employer)], ['label' => __('Total cost'), 'value' => $money($gross->plus($employer))]],
            'note' => __('Only approved and paid runs count. Total cost is the gross pay plus the employer\'s provident fund and gratuity provision.'),
        ];
    }

    /**
     * Every annual component (festival bonus) paid in the year's runs, by employee.
     *
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function festivalBonuses(int $propertyId, int $year): array
    {
        $codes = SalaryComponent::query()->where('frequency', ComponentFrequency::Annual->value)->pluck('code')->all();
        $rows = [];
        $total = BigDecimal::zero();
        $byComponent = [];

        $lines = PayslipLine::query()->with('payslip.run')->whereIn('code', $codes)
            ->whereHas('payslip', fn ($query) => $query->where('property_id', $propertyId)->whereHas('run', fn ($run) => $run->whereIn('status', [RunStatus::Approved->value, RunStatus::Paid->value])->whereYear('period', $year)))->get()
            ->sortBy(fn (PayslipLine $line): string => $line->payslip->run->period->format('Y-m').'|'.$line->code.'|'.$line->payslip->employee_name);

        foreach ($lines as $line) {
            $rows[] = [$line->payslip->run->period->format('F Y'), $line->name, $line->payslip->employee_no.' · '.$line->payslip->employee_name, $line->payslip->department_name ?? '—', number_format((float) (string) $line->amount, 2)];
            $total = $total->plus((string) $line->amount);
            $byComponent[$line->name] = ($byComponent[$line->name] ?? BigDecimal::zero())->plus((string) $line->amount);
        }

        return [
            'title' => __('Festival bonus register :year', ['year' => $year]),
            'columns' => [__('Month'), __('Bonus'), __('Employee'), __('Department'), __('Amount')],
            'rows' => $rows,
            'totals' => [__('Total'), '', '', '', number_format((float) (string) $total, 2)],
            'summary' => array_map(fn (string $name, BigDecimal $amount): array => ['label' => $name, 'value' => number_format((float) (string) $amount, 2)], array_keys($byComponent), array_values($byComponent)),
            'note' => __('The annual components (festival bonuses) paid with the year\'s approved and paid payroll runs.'),
        ];
    }

    /**
     * The year's service charge and tips pools with what was collected and distributed.
     *
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function pools(int $propertyId, int $year): array
    {
        $rows = [];
        $collected = BigDecimal::zero();
        $staff = BigDecimal::zero();
        $paid = BigDecimal::zero();

        foreach (ServiceChargePool::query()->where('property_id', $propertyId)->whereYear('period', $year)->orderBy('period')->orderBy('kind')->get() as $pool) {
            $distributed = $pool->status->value === 'distributed' ? (string) $pool->distributable : '0.00';
            $rows[] = [$pool->period->format('F Y'), $pool->kind->label(), $pool->rule->label(), number_format((float) (string) $pool->collected, 2), rtrim(rtrim((string) $pool->staff_percent, '0'), '.').'%', number_format((float) (string) $pool->distributable, 2), (string) $pool->employees_count, $pool->status->label(), number_format((float) $distributed, 2)];
            $collected = $collected->plus((string) $pool->collected);
            $staff = $staff->plus((string) $pool->distributable);
            $paid = $paid->plus($distributed);
        }

        $money = fn (BigDecimal $value): string => number_format((float) (string) $value, 2);

        return [
            'title' => __('Service charge and tips :year', ['year' => $year]),
            'columns' => [__('Month'), __('Pool'), __('Rule'), __('Collected'), __('Staff share'), __('To staff'), __('People'), __('Status'), __('Paid out')],
            'rows' => $rows,
            'totals' => [__('Total'), '', '', $money($collected), '', $money($staff), '', '', $money($paid)],
            'summary' => [['label' => __('Collected'), 'value' => $money($collected)], ['label' => __('To staff'), 'value' => $money($staff)], ['label' => __('Paid out'), 'value' => $money($paid)]],
            'note' => __('Paid out is what payroll runs that were approved have distributed; the rest is still held as a liability.'),
        ];
    }
}
