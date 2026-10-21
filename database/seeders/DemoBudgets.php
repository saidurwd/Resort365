<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\Accounting\Actions\SaveBudget;
use Modules\Accounting\Actions\SaveBudgetLines;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\FiscalYear;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * Budget demo (Step 7.2): the current fiscal year's budget for the property, with room and F&B revenue and the main costs by
 * month: fuller in the busy season (November to February) and quiet in the monsoon, with the F&B costs and the departments'
 * salaries following the sales.
 */
final class DemoBudgets
{
    /** Share of the year's budget each calendar month gets, in percent (twelve numbers adding to 100). */
    private const array SEASON = [1 => 11.0, 2 => 10.0, 3 => 8.5, 4 => 8.0, 5 => 6.0, 6 => 5.5, 7 => 5.5, 8 => 6.0, 9 => 6.5, 10 => 8.0, 11 => 11.0, 12 => 14.0];

    /**
     * system key, year total, department code or none (the payroll posts salaries by department, the rest without one).
     *
     * @var list<array{0: string, 1: int, 2: string|null}>
     */
    private const array LINES = [
        ['room_revenue', 12_000_000, null], ['food_revenue', 3_800_000, null], ['beverage_revenue', 1_400_000, null], ['spa_revenue', 600_000, null],
        ['food_cost', 1_300_000, null], ['beverage_cost', 320_000, null], ['utilities_expense', 900_000, null], ['repairs_expense', 420_000, null], ['office_expense', 180_000, null],
        ['salaries_expense', 700_000, 'FO'], ['salaries_expense', 650_000, 'HK'], ['salaries_expense', 700_000, 'FBS'], ['salaries_expense', 650_000, 'FBP'], ['salaries_expense', 400_000, 'ENG'], ['salaries_expense', 800_000, 'ADM'],
    ];

    public static function seed(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            $year = FiscalYear::query()->where('starts_on', '<=', CarbonImmutable::today()->toDateString())->where('ends_on', '>=', CarbonImmutable::today()->toDateString())->first();

            if (! $year instanceof FiscalYear || Budget::query()->where('fiscal_year_id', $year->id)->exists()) {
                return;
            }

            $budget = app(SaveBudget::class)->handle(null, ['fiscal_year_id' => $year->id, 'property_id' => $propertyId, 'name' => 'Budget '.$year->name, 'notes' => 'Demo budget: busy November to February, quiet in the monsoon.']);
            $departments = collect(app(DepartmentDirectory::class)->all(false))->pluck('id', 'code');
            $lines = [];

            foreach (self::LINES as [$key, $total, $department]) {
                $account = Account::query()->where('system_key', $key)->first();

                if (! $account instanceof Account) {
                    continue;
                }

                $months = [];

                for ($number = 1; $number <= 12; $number++) {
                    $calendar = $year->starts_on->copy()->addMonths($number - 1)->month;
                    $months[] = number_format($total * self::SEASON[$calendar] / 100, 2, '.', '');
                }

                $lines[] = ['account_id' => $account->id, 'department_id' => $department !== null ? ($departments[$department] ?? null) : null, 'months' => $months];
            }

            app(SaveBudgetLines::class)->handle($budget, $lines);
        });
    }
}
