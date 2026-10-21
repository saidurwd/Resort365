<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;
use Modules\Accounting\Services\BudgetKey;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * Replaces the lines of a budget (Step 7.2): income and expense accounts that can be posted to, each once for a department
 * (or for none), with twelve monthly amounts that are not negative. A line with every month at nothing is dropped.
 */
class SaveBudgetLines extends Action
{
    public function __construct(private readonly DepartmentDirectory $departments) {}

    /**
     * @param  list<array{account_id: int, department_id: int|null, months: list<string>}>  $lines
     *
     * @throws AccountingRuleViolated
     */
    public function handle(Budget $budget, array $lines): int
    {
        $accounts = Account::query()->whereIn('id', array_column($lines, 'account_id'))->get()->keyBy('id');
        $seen = [];
        $clean = [];

        foreach ($lines as $line) {
            $account = $accounts->get($line['account_id']) ?? throw new AccountingRuleViolated(__('That account does not exist.'));

            if ($account->is_group || ! $account->is_active || ! in_array($account->type, [AccountType::Income, AccountType::Expense], true)) {
                throw new AccountingRuleViolated(__(':account cannot be budgeted: choose an active income or expense account that can be posted to.', ['account' => $account->label()]));
            }

            if ($line['department_id'] !== null && ! $this->departments->find($line['department_id'])) {
                throw new AccountingRuleViolated(__('That department does not exist.'));
            }

            $key = BudgetKey::of($line['account_id'], $line['department_id']);

            if (isset($seen[$key])) {
                throw new AccountingRuleViolated(__(':account is in the budget twice for the same department.', ['account' => $account->label()]));
            }

            $seen[$key] = true;

            if (count($line['months']) !== 12) {
                throw new AccountingRuleViolated(__('A budget line needs an amount for each of the twelve months.'));
            }

            foreach ($line['months'] as $amount) {
                if (! is_numeric($amount) || BigDecimal::of($amount)->isNegative()) {
                    throw new AccountingRuleViolated(__('Budget amounts are numbers that are not negative.'));
                }
            }

            if (collect($line['months'])->contains(fn (string $amount): bool => ! BigDecimal::of($amount)->isZero())) {
                $clean[] = $line;
            }
        }

        return $this->transaction(function () use ($budget, $clean): int {
            BudgetLine::query()->where('budget_id', $budget->id)->delete();

            foreach ($clean as $line) {
                $row = ['budget_id' => $budget->id, 'account_id' => $line['account_id'], 'department_id' => $line['department_id']];

                foreach ($line['months'] as $index => $amount) {
                    $row['month_'.($index + 1)] = (string) BigDecimal::of($amount)->toScale(2);
                }

                BudgetLine::query()->create($row);
            }

            activity()->performedOn($budget)->withProperties(['lines' => count($clean)])->log('budget lines saved');

            return count($clean);
        });
    }
}
