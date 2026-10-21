<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Services\BudgetActuals;
use Modules\Accounting\Services\BudgetKey;

/**
 * Fills a budget from what the ledger shows for another fiscal year (Step 7.2), plus or minus a percent, replacing its lines. A
 * property's budget copies that property's figures; a company-wide budget the whole company's. Negative actuals (a credit note
 * larger than the sales) become nothing.
 */
class CopyBudgetFromActuals extends Action
{
    public function __construct(
        private readonly BudgetActuals $actuals,
        private readonly SaveBudgetLines $save,
    ) {}

    /**
     * @throws AccountingRuleViolated
     */
    public function handle(Budget $budget, FiscalYear $from, string $changePercent): int
    {
        if (! is_numeric($changePercent) || BigDecimal::of($changePercent)->isLessThan(-100) || BigDecimal::of($changePercent)->isGreaterThan(1000)) {
            throw new AccountingRuleViolated(__('The change is a percent from -100 to 1,000.'));
        }

        $factor = BigDecimal::of($changePercent)->dividedBy(100, 6, RoundingMode::HalfUp)->plus(1);
        $lines = [];

        foreach ($this->actuals->forYear($from->id, $budget->property_id) as $key => $months) {
            [$account, $department] = BudgetKey::split($key);
            $amounts = [];

            for ($month = 1; $month <= 12; $month++) {
                $actual = BigDecimal::of($months[$month] ?? '0');
                $amounts[] = $actual->isPositive() ? (string) $actual->multipliedBy($factor)->toScale(2, RoundingMode::HalfUp) : '0.00';
            }

            $lines[] = ['account_id' => $account, 'department_id' => $department, 'months' => $amounts];
        }

        if ($lines === []) {
            throw new AccountingRuleViolated(__('The ledger has no income or expense for :year to copy.', ['year' => $from->name]));
        }

        return $this->save->handle($budget, $lines);
    }
}
