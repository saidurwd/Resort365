<?php

/*
| Budget against actual (Step 7.2). "Done when": budget against actual shows variances by month. Income and expense, a favourable
| variance positive, an unbudgeted account against a budget of nothing.
*/
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\SaveBudget;
use Modules\Accounting\Actions\SaveBudgetLines;
use Modules\Accounting\DTOs\Report;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Services\BudgetReport;

require_once __DIR__.'/../Support/budget-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * The 2026 budget: room revenue 100,000 a month; F&B food revenue 40,000 in January only; salaries at Front Office 30,000 a month;
 * utilities 10,000 a month. The ledger: January room revenue 110,000, February 90,000; salaries at Front Office 28,000 in January and
 * 33,000 in February, and 1,500 at F&B Service in January (not budgeted); January water... none.
 *
 * @return array{0: Budget, 1: array<string, mixed>}
 */
function brScenario(): array
{
    $setup = bgSetup();
    $budget = booking(fn (): Budget => app(SaveBudget::class)->handle(null, ['fiscal_year_id' => $setup['y2026']->id, 'property_id' => bookingIds()['property'], 'name' => 'Budget 2026']));
    $line = fn (string $key, string $amount, ?int $department = null, array $months = []): array => ['account_id' => bgAccount($key), 'department_id' => $department, 'months' => $months !== [] ? $months : array_fill(0, 12, $amount)];
    booking(fn () => app(SaveBudgetLines::class)->handle($budget, [
        $line('room_revenue', '100000'), $line('food_revenue', '0', null, ['40000', ...array_fill(0, 11, '0')]), $line('salaries_expense', '30000', $setup['fo']), $line('utilities_expense', '10000'),
    ]));
    bgPost('room_revenue', '2026-01-10', '110000.00');
    bgPost('room_revenue', '2026-02-10', '90000.00');
    bgPost('salaries_expense', '2026-01-31', '28000.00', $setup['fo']);
    bgPost('salaries_expense', '2026-02-28', '33000.00', $setup['fo']);
    bgPost('salaries_expense', '2026-01-31', '1500.00', $setup['fbs']);

    return [$budget, $setup];
}

/**
 * The rows of a report as "label => cells without the label".
 *
 * @return array<string, list<string>>
 */
function brRows(Report $report): array
{
    $rows = [];

    foreach ($report->rows as $row) {
        $cells = $row['cells'];
        $rows[array_shift($cells)] = $cells;
    }

    return $rows;
}

it('compares each account with its budget for a run of months, with income, expenses and the net result', function (): void {
    [$budget] = brScenario();
    $report = booking(fn () => app(BudgetReport::class)->byAccount($budget, 1, 2, null));
    $rows = brRows($report);

    // January and February. Income: rooms 200,000 budgeted and 200,000 earned; food 40,000 budgeted and nothing earned.
    // Expenses: Front Office salaries 60,000 budgeted and 61,000 spent (1,000 over); F&B Service 1,500 unbudgeted; utilities 20,000 budgeted, 0 spent.
    expect($report->columns)->toBe(['Account', 'Budget', 'Actual', 'Variance', 'Variance %'])->and($rows['4110 · Room revenue'])->toBe(['200,000.00', '200,000.00', '0.00', '0.0%'])
        ->and($rows['4210 · Food revenue'])->toBe(['40,000.00', '0.00', '-40,000.00', '-100.0%'])->and($rows['Total income'])->toBe(['240,000.00', '200,000.00', '-40,000.00', '-16.7%'])
        ->and($rows['6110 · Salaries and wages · Front Office'])->toBe(['60,000.00', '61,000.00', '-1,000.00', '-1.7%'])->and($rows['6110 · Salaries and wages · F&B Service'])->toBe(['0.00', '1,500.00', '-1,500.00', '—'])
        ->and($rows['6310 · Electricity'])->toBe(['20,000.00', '0.00', '20,000.00', '100.0%'])->and($rows['Total expenses'])->toBe(['80,000.00', '62,500.00', '17,500.00', '21.9%'])
        ->and($rows['Net result'])->toBe(['160,000.00', '137,500.00', '-22,500.00', '-14.1%']);
});

it('shows one month, and one department', function (): void {
    [$budget, $setup] = brScenario();
    $january = brRows(booking(fn () => app(BudgetReport::class)->byAccount($budget, 1, 1, null)));
    $frontOffice = brRows(booking(fn () => app(BudgetReport::class)->byAccount($budget, 1, 12, $setup['fo'])));

    expect($january['4110 · Room revenue'])->toBe(['100,000.00', '110,000.00', '10,000.00', '10.0%'])->and($january['6110 · Salaries and wages · Front Office'])->toBe(['30,000.00', '28,000.00', '2,000.00', '6.7%'])
        ->and($frontOffice)->not->toHaveKey('6110 · Salaries and wages · F&B Service')->and($frontOffice)->not->toHaveKey('4110 · Room revenue')->and($frontOffice['6110 · Salaries and wages · Front Office'][0])->toBe('360,000.00');
});

it('shows the variance month by month for everything (done when)', function (): void {
    [$budget] = brScenario();
    $report = booking(fn () => app(BudgetReport::class)->byMonth($budget, null, null));
    $rows = brRows($report);

    // January: income 140,000 budgeted (100,000 rooms + 40,000 food) and 110,000 earned; expenses 40,000 budgeted and 29,500 spent: net 100,000 against 80,500.
    expect($report->columns[0])->toBe('Month')->and($rows['January 2026'])->toBe(['140,000.00', '110,000.00', '40,000.00', '29,500.00', '100,000.00', '80,500.00', '-19,500.00', '-19.5%'])
        ->and($rows['February 2026'])->toBe(['100,000.00', '90,000.00', '40,000.00', '33,000.00', '60,000.00', '57,000.00', '-3,000.00', '-5.0%'])
        ->and($rows['March 2026'])->toBe(['100,000.00', '0.00', '40,000.00', '0.00', '60,000.00', '0.00', '-60,000.00', '-100.0%'])->and($rows['Year'][0])->toBe('1,240,000.00');
});

it('shows one account month by month, and one account in one department', function (): void {
    [$budget, $setup] = brScenario();
    $room = brRows(booking(fn () => app(BudgetReport::class)->byMonth($budget, bgAccount('room_revenue'), null)));
    $salaries = brRows(booking(fn () => app(BudgetReport::class)->byMonth($budget, bgAccount('salaries_expense'), $setup['fo'])));
    $all = brRows(booking(fn () => app(BudgetReport::class)->byMonth($budget, bgAccount('salaries_expense'), null)));

    expect($room['January 2026'])->toBe(['100,000.00', '110,000.00', '10,000.00', '10.0%'])->and($room['February 2026'])->toBe(['100,000.00', '90,000.00', '-10,000.00', '-10.0%'])->and($room['Year'])->toBe(['1,200,000.00', '200,000.00', '-1,000,000.00', '-83.3%'])
        ->and($salaries['January 2026'])->toBe(['30,000.00', '28,000.00', '2,000.00', '6.7%'])->and($salaries['February 2026'])->toBe(['30,000.00', '33,000.00', '-3,000.00', '-10.0%'])
        ->and($all['January 2026'])->toBe(['30,000.00', '29,500.00', '500.00', '1.7%']);
});

it('has a note that explains the signs', function (): void {
    [$budget] = brScenario();
    $report = booking(fn () => app(BudgetReport::class)->byAccount($budget, 1, 12, null));

    expect($report->subtitle)->toContain('Budget 2026')->toContain('FY 2026')->and($report->notes[0])->toContain('favourable when positive');
});
