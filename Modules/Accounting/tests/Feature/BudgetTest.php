<?php

/*
| Budgets (Step 7.2): one per fiscal year and property, lines by account, department and month, copying last year's actuals, and
| what the ledger shows against them.
*/
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\CopyBudgetFromActuals;
use Modules\Accounting\Actions\DeleteBudget;
use Modules\Accounting\Actions\SaveBudget;
use Modules\Accounting\Actions\SaveBudgetLines;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;
use Modules\Accounting\Models\FiscalYear;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\BudgetActuals;
use Modules\Accounting\Services\BudgetKey;
use Modules\Property\Models\Property;

require_once __DIR__.'/../Support/budget-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * @param  array<string, mixed>  $data
 */
function bgBudget(FiscalYear $year, array $data = []): Budget
{
    return booking(fn (): Budget => app(SaveBudget::class)->handle(null, ['fiscal_year_id' => $year->id, 'property_id' => bookingIds()['property'], 'name' => 'Budget '.$year->name, ...$data]));
}

/**
 * A line with the same amount every month, or the given twelve.
 *
 * @param  string|list<string>  $months
 * @return array{account_id: int, department_id: int|null, months: list<string>}
 */
function bgLine(string $systemKey, string|array $months, ?int $department = null): array
{
    return ['account_id' => bgAccount($systemKey), 'department_id' => $department, 'months' => is_array($months) ? $months : array_fill(0, 12, $months)];
}

/**
 * @param  array{account_id: int, department_id: int|null, months: list<string>}  ...$lines
 */
function bgSave(Budget $budget, array ...$lines): int
{
    return booking(fn (): int => app(SaveBudgetLines::class)->handle($budget, $lines));
}

it('makes a budget for a fiscal year and property, once, and for the whole company besides', function (): void {
    $setup = bgSetup();
    $property = bgBudget($setup['y2026']);
    $company = bgBudget($setup['y2026'], ['property_id' => null, 'name' => 'Group budget']);

    expect($property->name)->toBe('Budget FY 2026')->and($company->property_id)->toBeNull()
        ->and(fn () => bgBudget($setup['y2026']))->toThrow(AccountingRuleViolated::class, 'There is a budget for FY 2026')
        ->and(fn () => bgBudget($setup['y2026'], ['property_id' => null]))->toThrow(AccountingRuleViolated::class, 'whole company')
        ->and(bgBudget($setup['y2025'])->fiscal_year_id)->toBe($setup['y2025']->id);
});

it('renames a budget but keeps its year and property', function (): void {
    $setup = bgSetup();
    $budget = bgBudget($setup['y2026']);
    $renamed = booking(fn (): Budget => app(SaveBudget::class)->handle($budget, ['name' => ' Plan B ', 'notes' => 'Revised', 'fiscal_year_id' => $setup['y2025']->id, 'property_id' => null]));

    expect($renamed->name)->toBe('Plan B')->and($renamed->notes)->toBe('Revised')->and($renamed->fiscal_year_id)->toBe($setup['y2026']->id)->and($renamed->property_id)->toBe(bookingIds()['property']);
});

it('saves the lines of income and expense accounts, drops those with nothing, and replaces what was there', function (): void {
    $setup = bgSetup();
    $budget = bgBudget($setup['y2026']);
    $count = bgSave($budget, bgLine('room_revenue', '100000.50'), bgLine('salaries_expense', '40000', $setup['fo']), bgLine('utilities_expense', '0'));

    $room = booking(fn (): BudgetLine => BudgetLine::query()->where('account_id', bgAccount('room_revenue'))->sole());
    expect($count)->toBe(2)->and($room->months())->toBe(array_fill(0, 12, '100000.50'))->and($room->department_id)->toBeNull();

    expect(bgSave($budget, bgLine('food_revenue', array_map(fn (int $m): string => (string) ($m * 1000), range(1, 12)))))->toBe(1)
        ->and(booking(fn (): int => BudgetLine::query()->count()))->toBe(1)->and(booking(fn () => BudgetLine::query()->sole()->month_12))->toBe('12000.00');
});

it('refuses lines that cannot be budgeted', function (): void {
    $setup = bgSetup();
    $budget = bgBudget($setup['y2026']);
    $group = booking(fn (): int => Account::query()->where('is_group', true)->where('type', 'income')->value('id'));
    $inactive = booking(function (): int {
        $account = Account::query()->where('system_key', 'spa_revenue')->firstOrFail();
        $account->forceFill(['is_active' => false])->save();

        return $account->id;
    });
    $bad = fn (array $line) => fn () => bgSave($budget, $line);

    expect($bad(bgLine('cash_on_hand', '100')))->toThrow(AccountingRuleViolated::class, 'cannot be budgeted')
        ->and($bad(['account_id' => $group, 'department_id' => null, 'months' => array_fill(0, 12, '1')]))->toThrow(AccountingRuleViolated::class, 'cannot be budgeted')
        ->and($bad(['account_id' => $inactive, 'department_id' => null, 'months' => array_fill(0, 12, '1')]))->toThrow(AccountingRuleViolated::class, 'cannot be budgeted')
        ->and($bad(['account_id' => 999999, 'department_id' => null, 'months' => array_fill(0, 12, '1')]))->toThrow(AccountingRuleViolated::class, 'does not exist')
        ->and($bad(bgLine('room_revenue', '100', 999999)))->toThrow(AccountingRuleViolated::class, 'department does not exist')
        ->and($bad(bgLine('room_revenue', '-5')))->toThrow(AccountingRuleViolated::class, 'not negative')
        ->and($bad(bgLine('room_revenue', 'lots')))->toThrow(AccountingRuleViolated::class, 'not negative')
        ->and($bad(bgLine('room_revenue', ['1', '2', '3'])))->toThrow(AccountingRuleViolated::class, 'twelve months')
        ->and(fn () => bgSave($budget, bgLine('room_revenue', '1', $setup['fo']), bgLine('room_revenue', '2', $setup['fo'])))->toThrow(AccountingRuleViolated::class, 'twice');
    expect(bgSave($budget, bgLine('room_revenue', '1', $setup['fo']), bgLine('room_revenue', '2', $setup['fbs']), bgLine('room_revenue', '3')))->toBe(3);
});

it('reads the ledger by account, department and month, for the income and expense accounts of the year', function (): void {
    $setup = bgSetup();
    $property = bookingIds()['property'];
    bgPost('room_revenue', '2026-01-10', '60000.00');
    bgPost('room_revenue', '2026-01-20', '40000.00');
    bgPost('room_revenue', '2026-02-05', '70000.00');
    bgPost('room_revenue', '2026-02-06', '5000.00', reverse: true);
    bgPost('salaries_expense', '2026-01-31', '30000.00', $setup['fo']);
    bgPost('salaries_expense', '2026-01-31', '20000.00', $setup['fbs']);
    bgPost('room_revenue', '2025-12-31', '999.00');
    $actuals = booking(fn (): array => app(BudgetActuals::class)->forYear(FiscalYear::query()->where('name', 'FY 2026')->value('id'), $property));

    expect($actuals[BudgetKey::of(bgAccount('room_revenue'), null)])->toBe([1 => '100000.00', 2 => '65000.00'])
        ->and($actuals[BudgetKey::of(bgAccount('salaries_expense'), $setup['fo'])])->toBe([1 => '30000.00'])->and($actuals[BudgetKey::of(bgAccount('salaries_expense'), $setup['fbs'])])->toBe([1 => '20000.00'])
        ->and(array_key_exists(BudgetKey::of(bgAccount('cash_on_hand'), null), $actuals))->toBeFalse();
});

it('counts only the budget\'s property for a property budget, and every property for a company-wide one', function (): void {
    $setup = bgSetup();
    $other = booking(fn (): int => Property::factory()->create(['code' => 'GVR'])->id);
    bgPost('room_revenue', '2026-03-10', '1000.00');
    bgPost('room_revenue', '2026-03-11', '300.00', propertyId: $other);
    $year = $setup['y2026']->id;
    $actuals = fn (?int $property): array => booking(fn (): array => app(BudgetActuals::class)->forYear($year, $property));
    $key = BudgetKey::of(bgAccount('room_revenue'), null);

    expect($actuals(bookingIds()['property'])[$key][3])->toBe('1000.00')->and($actuals($other)[$key][3])->toBe('300.00')->and($actuals(null)[$key][3])->toBe('1300.00');
});

it('fills a budget from another year\'s actuals plus a percent, and refuses an empty year or a silly percent', function (): void {
    $setup = bgSetup();
    bgPost('room_revenue', '2025-01-15', '100000.00');
    bgPost('room_revenue', '2025-03-15', '50000.00');
    bgPost('food_cost', '2025-03-20', '10000.00', $setup['fbs']);
    bgPost('room_revenue', '2025-04-02', '3000.00', reverse: true);
    $budget = bgBudget($setup['y2026']);
    $copy = fn (string $percent, ?FiscalYear $from = null) => booking(fn (): int => app(CopyBudgetFromActuals::class)->handle($budget, $from ?? $setup['y2025'], $percent));

    expect($copy('10'))->toBe(2);
    $room = booking(fn (): BudgetLine => BudgetLine::query()->where('account_id', bgAccount('room_revenue'))->sole());
    $food = booking(fn (): BudgetLine => BudgetLine::query()->where('account_id', bgAccount('food_cost'))->sole());

    // January 100,000 and March 50,000 become 110,000 and 55,000; April's credit note was larger than nothing, so it is nothing.
    expect($room->months())->toBe(['110000.00', '0.00', '55000.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00'])->and($food->department_id)->toBe($setup['fbs'])->and($food->month_3)->toBe('11000.00')
        ->and($copy('-100'))->toBe(0);

    expect(fn () => $copy('0', $setup['y2026']))->toThrow(AccountingRuleViolated::class, 'no income or expense')
        ->and(fn () => $copy('2000'))->toThrow(AccountingRuleViolated::class, '-100 to 1,000')->and(fn () => $copy('lots'))->toThrow(AccountingRuleViolated::class, '-100 to 1,000');
});

it('deletes a budget with its lines and leaves the ledger alone', function (): void {
    $setup = bgSetup();
    bgPost('room_revenue', '2026-01-10', '100.00');
    $budget = bgBudget($setup['y2026']);
    bgSave($budget, bgLine('room_revenue', '1000'));

    booking(fn () => app(DeleteBudget::class)->handle($budget));

    expect(booking(fn (): int => Budget::query()->count()))->toBe(0)->and(booking(fn (): int => BudgetLine::query()->count()))->toBe(0)->and(booking(fn (): int => JournalEntry::query()->count()))->toBe(1);
});
