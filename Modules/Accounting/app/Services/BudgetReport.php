<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Modules\Accounting\DTOs\Report;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Budget against actual (Step 7.2), as a `Report` (the shape the financial reports show and export).
 *
 * - By account: each income and expense account (and department) with the budget, the actual, the variance and the variance
 *   percent for a run of months, income and expense each added up, and the net result. An account with ledger activity but no
 *   budget line is shown too, against a budget of nothing.
 * - By month: the twelve months of the year, for everything or for one account.
 *
 * Variance is favourable when positive (more income than planned, less expense than planned).
 */
class BudgetReport
{
    public function __construct(
        private readonly BudgetActuals $actuals,
        private readonly BudgetVariance $variance,
        private readonly DepartmentDirectory $departments,
        private readonly PropertyDirectory $properties,
    ) {}

    public function byAccount(Budget $budget, int $firstMonth, int $lastMonth, ?int $departmentId): Report
    {
        $budget->loadMissing('fiscalYear.periods');
        [$firstMonth, $lastMonth] = $this->range($firstMonth, $lastMonth);
        $plan = $this->plan($budget);
        $actual = $this->actuals->forYear($budget->fiscal_year_id, $budget->property_id);
        $keys = array_values(array_unique([...array_keys($plan), ...array_keys($actual)]));
        $accounts = Account::query()->whereIn('id', array_map(fn (string $key): int => BudgetKey::split($key)[0], $keys))->get()->keyBy('id');
        $names = collect($this->departments->all(false))->pluck('name', 'id');
        $keys = array_values(array_filter($keys, fn (string $key): bool => $departmentId === null || BudgetKey::split($key)[1] === $departmentId));
        usort($keys, fn (string $a, string $b): int => [$accounts[BudgetKey::split($a)[0]]->code, BudgetKey::split($a)[1] ?? 0] <=> [$accounts[BudgetKey::split($b)[0]]->code, BudgetKey::split($b)[1] ?? 0]);
        $rows = [];
        $totals = ['income' => ['budget' => BigDecimal::zero(), 'actual' => BigDecimal::zero()], 'expense' => ['budget' => BigDecimal::zero(), 'actual' => BigDecimal::zero()]];

        foreach ([AccountType::Income, AccountType::Expense] as $type) {
            $block = [];

            foreach ($keys as $key) {
                [$accountId, $dept] = BudgetKey::split($key);
                $account = $accounts[$accountId];

                if ($account->type !== $type) {
                    continue;
                }

                $b = $this->variance->sum($plan[$key] ?? [], $firstMonth, $lastMonth);
                $a = $this->variance->sum($actual[$key] ?? [], $firstMonth, $lastMonth);

                if (BigDecimal::of($b)->isZero() && BigDecimal::of($a)->isZero()) {
                    continue;
                }

                $block[] = ['cells' => [$account->label().($dept !== null ? ' · '.($names[$dept] ?? '#'.$dept) : ''), ...$this->cells($type->value, $b, $a)], 'indent' => 1];
                $totals[$type->value]['budget'] = $totals[$type->value]['budget']->plus($b);
                $totals[$type->value]['actual'] = $totals[$type->value]['actual']->plus($a);
            }

            $rows[] = ['cells' => [$type === AccountType::Income ? __('Income') : __('Expenses'), '', '', '', ''], 'bold' => true];
            array_push($rows, ...$block);
            $rows[] = ['cells' => [$type === AccountType::Income ? __('Total income') : __('Total expenses'), ...$this->cells($type->value, (string) $totals[$type->value]['budget'], (string) $totals[$type->value]['actual'])], 'bold' => true];
        }

        $netBudget = $totals['income']['budget']->minus($totals['expense']['budget']);
        $netActual = $totals['income']['actual']->minus($totals['expense']['actual']);
        $rows[] = ['cells' => [__('Net result'), ...$this->cells('income', (string) $netBudget, (string) $netActual)], 'bold' => true];

        return new Report(
            __('Budget against actual'), $this->subtitle($budget).' · '.$this->monthName($budget, $firstMonth).($firstMonth === $lastMonth ? '' : ' – '.$this->monthName($budget, $lastMonth)),
            [__('Account'), __('Budget'), __('Actual'), __('Variance'), __('Variance %')], $rows, $this->notes(),
        );
    }

    public function byMonth(Budget $budget, ?int $accountId, ?int $departmentId): Report
    {
        $budget->loadMissing('fiscalYear.periods');
        $plan = $this->plan($budget);
        $actual = $this->actuals->forYear($budget->fiscal_year_id, $budget->property_id);
        $accounts = Account::query()->whereIn('id', array_map(fn (string $key): int => BudgetKey::split($key)[0], array_values(array_unique([...array_keys($plan), ...array_keys($actual)]))))->get()->keyBy('id');
        $rows = [];

        if ($accountId !== null) {
            $account = $accounts->get($accountId) ?? Account::query()->findOrFail($accountId);
            $type = $account->type->value;
            $columns = [__('Month'), __('Budget'), __('Actual'), __('Variance'), __('Variance %')];
            $sumB = BigDecimal::zero();
            $sumA = BigDecimal::zero();

            for ($month = 1; $month <= 12; $month++) {
                $b = $this->pick($plan, $accountId, $departmentId, $month);
                $a = $this->pick($actual, $accountId, $departmentId, $month);
                $rows[] = ['cells' => [$this->monthName($budget, $month), ...$this->cells($type, $b, $a)]];
                $sumB = $sumB->plus($b);
                $sumA = $sumA->plus($a);
            }

            $rows[] = ['cells' => [__('Year'), ...$this->cells($type, (string) $sumB, (string) $sumA)], 'bold' => true];
            $subtitle = $this->subtitle($budget).' · '.$account->label();
        } else {
            $columns = [__('Month'), __('Income budget'), __('Income actual'), __('Expense budget'), __('Expense actual'), __('Net budget'), __('Net actual'), __('Net variance'), __('Variance %')];
            $year = ['ib' => BigDecimal::zero(), 'ia' => BigDecimal::zero(), 'eb' => BigDecimal::zero(), 'ea' => BigDecimal::zero()];

            for ($month = 1; $month <= 12; $month++) {
                $month4 = [
                    'ib' => $this->total($plan, $accounts, 'income', $departmentId, $month), 'ia' => $this->total($actual, $accounts, 'income', $departmentId, $month),
                    'eb' => $this->total($plan, $accounts, 'expense', $departmentId, $month), 'ea' => $this->total($actual, $accounts, 'expense', $departmentId, $month),
                ];
                $rows[] = ['cells' => [$this->monthName($budget, $month), ...$this->netCells($month4)]];

                foreach ($month4 as $name => $value) {
                    $year[$name] = $year[$name]->plus($value);
                }
            }

            $rows[] = ['cells' => [__('Year'), ...$this->netCells(array_map(fn (BigDecimal $value): string => (string) $value, $year))], 'bold' => true];
            $subtitle = $this->subtitle($budget);
        }

        return new Report(__('Budget against actual by month'), $subtitle, $columns, $rows, $this->notes());
    }

    /**
     * The budget as BudgetKey => month => amount.
     *
     * @return array<string, array<int, string>>
     */
    public function plan(Budget $budget): array
    {
        $plan = [];

        foreach (BudgetLine::query()->where('budget_id', $budget->id)->get() as $line) {
            $months = [];

            foreach (range(1, 12) as $month) {
                $months[$month] = (string) $line->getAttribute('month_'.$month);
            }

            $plan[BudgetKey::of($line->account_id, $line->department_id)] = $months;
        }

        return $plan;
    }

    /**
     * @return list<string>
     */
    private function cells(string $type, string $budget, string $actual): array
    {
        $variance = $this->variance->of($type, $budget, $actual);

        return [$this->money($budget), $this->money($actual), $this->money($variance['amount']), $variance['percent'] === null ? '—' : $variance['percent'].'%'];
    }

    /**
     * @param  array{ib: string|BigDecimal, ia: string|BigDecimal, eb: string|BigDecimal, ea: string|BigDecimal}  $m
     * @return list<string>
     */
    private function netCells(array $m): array
    {
        $netBudget = BigDecimal::of((string) $m['ib'])->minus((string) $m['eb']);
        $netActual = BigDecimal::of((string) $m['ia'])->minus((string) $m['ea']);
        $variance = $this->variance->of('income', (string) $netBudget, (string) $netActual);

        return [$this->money((string) $m['ib']), $this->money((string) $m['ia']), $this->money((string) $m['eb']), $this->money((string) $m['ea']), $this->money((string) $netBudget), $this->money((string) $netActual), $this->money($variance['amount']), $variance['percent'] === null ? '—' : $variance['percent'].'%'];
    }

    /**
     * One account's amount for a month, over all departments or one.
     *
     * @param  array<string, array<int, string>>  $source
     */
    private function pick(array $source, int $accountId, ?int $departmentId, int $month): string
    {
        $sum = BigDecimal::zero();

        foreach ($source as $key => $months) {
            [$account, $dept] = BudgetKey::split($key);

            if ($account === $accountId && ($departmentId === null || $dept === $departmentId)) {
                $sum = $sum->plus($months[$month] ?? '0');
            }
        }

        return (string) $sum->toScale(2);
    }

    /**
     * Everything of one type for a month.
     *
     * @param  array<string, array<int, string>>  $source
     * @param  Collection<int, Account>  $accounts
     */
    private function total(array $source, $accounts, string $type, ?int $departmentId, int $month): string
    {
        $sum = BigDecimal::zero();

        foreach ($source as $key => $months) {
            [$account, $dept] = BudgetKey::split($key);

            if (($accounts[$account]->type->value ?? null) === $type && ($departmentId === null || $dept === $departmentId)) {
                $sum = $sum->plus($months[$month] ?? '0');
            }
        }

        return (string) $sum->toScale(2);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function range(int $first, int $last): array
    {
        $first = max(1, min(12, $first));

        return [$first, max($first, min(12, $last))];
    }

    private function monthName(Budget $budget, int $month): string
    {
        return $budget->fiscalYear->periods->firstWhere('number', $month)->name ?? (string) $month;
    }

    private function subtitle(Budget $budget): string
    {
        $budget->loadMissing('fiscalYear.periods');
        $scope = $budget->property_id === null ? __('All properties') : ($this->properties->find($budget->property_id)->name ?? '#'.$budget->property_id);

        return $budget->name.' · '.$budget->fiscalYear->name.' · '.$scope;
    }

    /**
     * @return list<string>
     */
    private function notes(): array
    {
        return [__('Variance is favourable when positive: more income than budgeted, or less expense. Actuals are the posted ledger lines of the budget\'s property (all properties for a company-wide budget). An account with activity but no budget line is shown against a budget of nothing.')];
    }

    private function money(string $value): string
    {
        return number_format((float) $value, 2);
    }
}
