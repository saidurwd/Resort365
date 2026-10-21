<?php

/*
| Budgets on screen (Step 7.2): the list, making a budget, the lines grid, copying the actuals, the budget against actual and its
| files, and who may do what.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\SaveBudget;
use Modules\Accounting\Actions\SaveBudgetLines;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/budget-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * @param  array<string, mixed>  $setup
 */
function bsBudget(array $setup): Budget
{
    return booking(fn (): Budget => app(SaveBudget::class)->handle(null, ['fiscal_year_id' => $setup['y2026']->id, 'property_id' => bookingIds()['property'], 'name' => 'Budget 2026']));
}

/** @return array{lines: array<int, array<string, mixed>>} */
function bsLines(int $department): array
{
    return ['lines' => [
        ['account_id' => bgAccount('room_revenue'), 'department_id' => '', 'months' => array_fill(0, 12, '100000')],
        ['account_id' => bgAccount('salaries_expense'), 'department_id' => $department, 'months' => ['30000', '', ...array_fill(0, 10, '31000')]],
    ]];
}

it('makes a budget from the screen and refuses a second one for the same year and property', function (): void {
    $setup = bgSetup();
    staffUser(DefaultRole::Accountant);

    get(tenantUrl('sunrise', '/accounting/budgets'))->assertOk()->assertSeeHtml('data-new-budget')->assertSee('No budgets yet');
    get(tenantUrl('sunrise', '/accounting/budgets/new'))->assertOk()->assertSeeHtml('data-budget-form');
    post(tenantUrl('sunrise', '/accounting/budgets'), ['name' => ''])->assertSessionHasErrors(['name', 'fiscal_year_id']);
    post(tenantUrl('sunrise', '/accounting/budgets'), ['fiscal_year_id' => 999999, 'name' => 'x'])->assertSessionHasErrors('fiscal_year_id');
    post(tenantUrl('sunrise', '/accounting/budgets'), ['fiscal_year_id' => $setup['y2026']->id, 'property_id' => bookingIds()['property'], 'name' => 'Budget 2026'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/accounting/budgets'), ['fiscal_year_id' => $setup['y2026']->id, 'property_id' => bookingIds()['property'], 'name' => 'Again'])->assertSessionHas('error');

    $budget = booking(fn (): Budget => Budget::query()->sole());
    get(tenantUrl('sunrise', '/accounting/budgets'))->assertOk()->assertSeeHtml("data-budget=\"{$budget->id}\"")->assertSee('Sunrise Cox');
});

it('shows the lines grid, saves the lines and renames the budget', function (): void {
    $setup = bgSetup();
    $budget = bsBudget($setup);
    staffUser(DefaultRole::Accountant);

    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}"))->assertOk()->assertSeeHtml('data-lines-form')->assertSeeHtml('data-add-line')->assertSeeHtml('data-copy-form')->assertSee('Room revenue');
    put(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/lines"), ['lines' => [['account_id' => 999999, 'months' => array_fill(0, 12, '1')]]])->assertSessionHasErrors('lines.0.account_id');
    put(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/lines"), ['lines' => [['account_id' => bgAccount('room_revenue'), 'months' => ['1', '2']]]])->assertSessionHasErrors('lines.0.months');
    put(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/lines"), ['lines' => [['account_id' => bgAccount('cash_on_hand'), 'months' => array_fill(0, 12, '1')]]])->assertSessionHas('error');
    put(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/lines"), bsLines($setup['fo']))->assertSessionHas('success');

    $salaries = booking(fn (): BudgetLine => BudgetLine::query()->where('account_id', bgAccount('salaries_expense'))->sole());
    expect($salaries->month_1)->toBe('30000.00')->and($salaries->month_2)->toBe('0.00')->and($salaries->month_3)->toBe('31000.00')->and($salaries->department_id)->toBe($setup['fo']);

    put(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}"), ['name' => 'Plan B'])->assertSessionHas('success');
    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}"))->assertSee('Plan B');
});

it('copies last year\'s actuals into the budget', function (): void {
    $setup = bgSetup();
    bgPost('room_revenue', '2025-02-10', '80000.00');
    $budget = bsBudget($setup);
    staffUser(DefaultRole::Accountant);

    post(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/copy"), ['from_year_id' => $setup['y2025']->id, 'change' => '5'])->assertSessionHas('success');
    post(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/copy"), ['from_year_id' => $setup['y2025']->id, 'change' => '5000'])->assertSessionHasErrors('change');
    post(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/copy"), ['from_year_id' => 999999, 'change' => '5'])->assertSessionHasErrors('from_year_id');

    expect(booking(fn () => BudgetLine::query()->sole()->month_2))->toBe('84000.00');
});

it('deletes a budget', function (): void {
    $budget = bsBudget(bgSetup());
    staffUser(DefaultRole::Accountant);

    delete(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}"))->assertRedirect(tenantUrl('sunrise', '/accounting/budgets'));

    expect(booking(fn (): int => Budget::query()->count()))->toBe(0);
});

it('shows the budget against actual by account and by month, with the exports of the financial reports', function (): void {
    $setup = bgSetup();
    $budget = bsBudget($setup);
    booking(fn () => app(SaveBudgetLines::class)->handle($budget, [
        ['account_id' => bgAccount('room_revenue'), 'department_id' => null, 'months' => array_fill(0, 12, '100000')],
    ]));
    bgPost('room_revenue', '2026-01-10', '110000.00');
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/report?from=1&to=1"))->assertOk()->assertSeeHtml('data-budget-vs-actual="accounts"')->assertSee('110,000.00')->assertSee('10,000.00')->assertSee('10.0%')->assertSee('favourable when positive');
    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/report?view=months"))->assertOk()->assertSeeHtml('data-budget-vs-actual="months"')->assertSee('January 2026')->assertSee('Net variance');
    get(tenantUrl('sunrise', '/accounting/budgets/'.$budget->id.'/report?view=months&account='.bgAccount('room_revenue')))->assertOk()->assertSee('Variance %');
    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/report?from=5&to=2"))->assertSessionHasErrors('to');
    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/report?view=forecast"))->assertSessionHasErrors('view');

    $csv = get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/report?from=1&to=1&export=csv"));
    $csv->assertOk();
    expect($csv->getContent())->toContain('Budget against actual')->toContain('4110 · Room revenue')->toContain('110000');
    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/report?from=1&to=1&export=xlsx"))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/report?from=1&to=1&export=pdf"))->assertOk();
});

it('lets the General Manager look but not change, and keeps others out', function (): void {
    $setup = bgSetup();
    $budget = bsBudget($setup);

    staffUser(DefaultRole::GeneralManager);
    get(tenantUrl('sunrise', '/accounting/budgets'))->assertOk()->assertDontSeeHtml('data-new-budget');
    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}"))->assertOk()->assertDontSeeHtml('data-save-lines')->assertDontSeeHtml('data-copy-form');
    get(tenantUrl('sunrise', '/accounting/budgets/new'))->assertForbidden();
    post(tenantUrl('sunrise', '/accounting/budgets'), ['fiscal_year_id' => $setup['y2025']->id, 'name' => 'x'])->assertForbidden();
    put(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/lines"), bsLines($setup['fo']))->assertForbidden();
    post(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/copy"), ['from_year_id' => $setup['y2025']->id, 'change' => '0'])->assertForbidden();
    delete(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}"))->assertForbidden();

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/accounting/budgets'))->assertForbidden();
    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}"))->assertForbidden();
    get(tenantUrl('sunrise', "/accounting/budgets/{$budget->id}/report"))->assertForbidden();
});
