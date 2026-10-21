<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    protected $model = BudgetLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['budget_id' => Budget::factory(), 'account_id' => Account::factory()->state(['type' => AccountType::Income]), 'department_id' => null, 'month_1' => '1000.00', 'month_2' => '1000.00', 'month_3' => '1000.00', 'month_4' => '1000.00', 'month_5' => '1000.00', 'month_6' => '1000.00', 'month_7' => '1000.00', 'month_8' => '1000.00', 'month_9' => '1000.00', 'month_10' => '1000.00', 'month_11' => '1000.00', 'month_12' => '1000.00'];
    }
}
