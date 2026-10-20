<?php

/*
| Payroll reports (Step 6.6): payroll cost by department, the festival bonus register, and service charge and tips; on screen
| and as CSV, for the people who may see them.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Payroll\Actions\CalculatePayroll;
use Modules\Payroll\Actions\CreatePayrollRun;
use Modules\Payroll\Actions\FinalisePool;
use Modules\Payroll\Actions\PreparePool;
use Modules\Payroll\Actions\SubmitPayrollRun;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolRule;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Services\PayrollCostReports;

use function Pest\Laravel\get;

require_once __DIR__.'/../Support/payroll-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    prPrepare();
});

it('adds the approved runs up by department, and ignores a run still being prepared', function (): void {
    prMarch();
    prApprove(prRun());
    $report = booking(fn (): array => app(PayrollCostReports::class)->costByDepartment(bookingIds()['property'], '2026-03', '2026-03'));

    // Rahim 42,980.77 gross, New 17,805.45; the employer adds provident fund and gratuity on top.
    expect($report['totals'][1])->toBe('2')->and($report['totals'][2])->toBe('60,786.22')->and($report['totals'][5])->toBe('55,420.68')->and($report['rows'])->not->toBeEmpty()
        ->and(booking(fn (): array => app(PayrollCostReports::class)->costByDepartment(bookingIds()['property'], '2026-01', '2026-02'))['rows'])->toBe([]);

    booking(fn () => app(CreatePayrollRun::class)->handle(bookingIds()['property'], '2026-04', [], null, 1));
    expect(booking(fn (): array => app(PayrollCostReports::class)->costByDepartment(bookingIds()['property'], '2026-04', '2026-04'))['rows'])->toBe([]);
});

it('registers the festival bonuses paid in the year', function (): void {
    prMarch();
    $run = booking(fn (): PayrollRun => app(CalculatePayroll::class)->handle(booking(fn (): PayrollRun => app(CreatePayrollRun::class)->handle(bookingIds()['property'], '2026-03', ['FBONUS'], null, 1))));
    booking(fn () => app(SubmitPayrollRun::class)->handle($run, prUser()->id));
    $report = booking(fn (): array => app(PayrollCostReports::class)->festivalBonuses(bookingIds()['property'], 2026));

    expect($report['rows'])->toBe([]);

    booking(fn () => app(ApprovalEngine::class)->approve((int) prFresh($run)->approval_request_id, prUser(DefaultRole::Accountant)->id));
    $report = booking(fn (): array => app(PayrollCostReports::class)->festivalBonuses(bookingIds()['property'], 2026));

    expect($report['rows'])->toHaveCount(2)->and($report['rows'][0][1])->toBe('Festival bonus')->and($report['rows'][0][2])->toContain('EMP-0002')->and($report['rows'][0][4])->toBe('20,000.00')->and($report['rows'][1][4])->toBe('25,000.00')
        ->and($report['totals'][4])->toBe('45,000.00');
});

it('lists the pools with what was collected and what payroll has paid out', function (): void {
    prMarch();
    prCollect(['service_charge' => '100000.00']);
    $pool = booking(fn () => app(PreparePool::class)->handle(bookingIds()['property'], '2026-03', PoolKind::ServiceCharge, PoolRule::Equal, '80', 1));
    booking(fn () => app(FinalisePool::class)->handle($pool, 1));
    $before = booking(fn (): array => app(PayrollCostReports::class)->pools(bookingIds()['property'], 2026));
    prApprove(prRun());
    $after = booking(fn (): array => app(PayrollCostReports::class)->pools(bookingIds()['property'], 2026));

    expect($before['rows'][0][3])->toBe('100,000.00')->and($before['rows'][0][4])->toBe('80%')->and($before['rows'][0][5])->toBe('80,000.00')->and($before['totals'][8])->toBe('0.00')
        ->and($after['totals'][8])->toBe('80,000.00')->and($after['rows'][0][7])->toBe('Distributed');
});

it('shows the reports on screen and as CSV to those who may see them', function (): void {
    prMarch();
    prApprove(prRun());
    staffUser(DefaultRole::Accountant);

    get(tenantUrl('sunrise', '/payroll/reports'))->assertOk()->assertSeeHtml('data-report="cost"');
    get(tenantUrl('sunrise', '/payroll/reports/cost?from=2026-03&to=2026-03'))->assertOk()->assertSee('60,786.22');
    get(tenantUrl('sunrise', '/payroll/reports/cost?from=2026-05&to=2026-03'))->assertSessionHasErrors('to');
    get(tenantUrl('sunrise', '/payroll/reports/festival-bonuses?year=2026'))->assertOk();
    get(tenantUrl('sunrise', '/payroll/reports/service-charge-and-tips?year=2026'))->assertOk();

    $csv = get(tenantUrl('sunrise', '/payroll/reports/cost?from=2026-03&to=2026-03&export=csv'));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('Department')->toContain('60,786.22');
});

it('keeps the reports from those who may not see them', function (): void {
    staffUser(DefaultRole::FrontDeskAgent);

    get(tenantUrl('sunrise', '/payroll/reports'))->assertForbidden();
    get(tenantUrl('sunrise', '/payroll/reports/cost'))->assertForbidden();
});
