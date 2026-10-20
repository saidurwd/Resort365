<?php

/*
| Payroll runs, payslips and loans on screen (Step 6.5): the run page, the payslip page and PDF, the emails, the bank file and
| register, loans and their installments, and who may use them.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Modules\Payroll\Actions\SkipInstallment;
use Modules\Payroll\Actions\WaiveInstallment;
use Modules\Payroll\Enums\InstallmentStatus;
use Modules\Payroll\Enums\LoanKind;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Events\LoanDisbursed;
use Modules\Payroll\Events\LoanInstallmentWaived;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Jobs\SendPayslipEmail;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstallment;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Notifications\PayslipMessage;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../Support/payroll-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    prPrepare();
});

function prSlipId(PayrollRun $run, string $no = 'EMP-0001'): int
{
    return booking(fn (): int => Payslip::query()->where('payroll_run_id', $run->id)->where('employee_no', $no)->value('id'));
}

it('lists runs, opens one for a month and shows its payslips and totals', function (): void {
    prMarch();
    staffUser(DefaultRole::PayrollOfficer);

    get(tenantUrl('sunrise', '/payroll/runs'))->assertOk();
    get(tenantUrl('sunrise', '/payroll/runs/create'))->assertOk();
    post(tenantUrl('sunrise', '/payroll/runs'), ['month' => 'March'])->assertSessionHasErrors('month');
    post(tenantUrl('sunrise', '/payroll/runs'), ['month' => '2026-03'])->assertSessionHas('success');
    $run = booking(fn (): PayrollRun => PayrollRun::query()->firstOrFail());
    post(tenantUrl('sunrise', "/payroll/runs/{$run->id}/calculate"))->assertSessionHas('success');

    get(tenantUrl('sunrise', "/payroll/runs/{$run->id}"))->assertOk()->assertSee('Rahim')->assertSee('38,647.43')->assertSee('55,420.68')->assertSee('Zed');
    get(tenantUrl('sunrise', '/payroll/runs'))->assertOk()->assertSee($run->run_no);
    post(tenantUrl('sunrise', '/payroll/runs'), ['month' => '2026-03'])->assertSessionHas('error');
});

it('shows a payslip, adjusts it and downloads the PDF', function (): void {
    prMarch();
    $run = prRun();
    staffUser(DefaultRole::PayrollOfficer);
    $id = prSlipId($run);

    get(tenantUrl('sunrise', "/payroll/payslips/{$id}"))->assertOk()->assertSee('Rahim')->assertSee('BASIC')->assertSee('38,647.43');
    post(tenantUrl('sunrise', "/payroll/payslips/{$id}/adjustments"), ['kind' => 'earning', 'name' => '', 'amount' => '0', 'note' => ''])->assertSessionHasErrors(['name', 'amount', 'note']);
    post(tenantUrl('sunrise', "/payroll/payslips/{$id}/adjustments"), ['kind' => 'earning', 'name' => 'Bonus', 'amount' => '1000', 'note' => 'Good month'])->assertSessionHas('success');
    $line = booking(fn (): int => Payslip::query()->findOrFail($id)->lines()->where('code', 'ADJ')->value('id'));
    delete(tenantUrl('sunrise', "/payroll/payslips/{$id}/adjustments/{$line}"))->assertSessionHas('success');

    $pdf = get(tenantUrl('sunrise', "/payroll/payslips/{$id}/pdf"));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('pdf');
});

it('sends the run for approval and pays it from the screen, then locks the screens', function (): void {
    prMarch();
    $run = prRun();
    staffUser(DefaultRole::PayrollOfficer);

    post(tenantUrl('sunrise', "/payroll/runs/{$run->id}/pay"), ['payment_method' => 'bank_transfer', 'paid_on' => now()->toDateString()])->assertForbidden();
    post(tenantUrl('sunrise', "/payroll/runs/{$run->id}/submit"))->assertSessionHas('success');
    expect(prFresh($run)->status->value)->toBe('pending');
    post(tenantUrl('sunrise', "/payroll/runs/{$run->id}/calculate"))->assertSessionHas('error');
    delete(tenantUrl('sunrise', "/payroll/runs/{$run->id}"))->assertSessionHas('error');
    post(tenantUrl('sunrise', '/payroll/payslips/'.prSlipId($run).'/adjustments'), ['kind' => 'earning', 'name' => 'Bonus', 'amount' => '10', 'note' => 'x'])->assertSessionHas('error');
});

it('pays an approved run from the screen with the right permission, and validates the form', function (): void {
    prMarch();
    $run = prApprove(prRun());
    staffUser(DefaultRole::Accountant);

    post(tenantUrl('sunrise', "/payroll/runs/{$run->id}/pay"), ['payment_method' => 'wrong', 'paid_on' => 'x'])->assertSessionHasErrors(['payment_method', 'paid_on']);
    post(tenantUrl('sunrise', "/payroll/runs/{$run->id}/pay"), ['payment_method' => 'bank_transfer', 'paid_on' => now()->toDateString(), 'payment_reference' => 'BEFTN-1'])->assertSessionHas('success');
    expect(prFresh($run)->status->value)->toBe('paid');
});

it('queues payslip emails once for people with an address, and again on request', function (): void {
    prMarch();
    $run = prApprove(prRun());
    staffUser(DefaultRole::PayrollOfficer);
    Queue::fake();

    post(tenantUrl('sunrise', "/payroll/runs/{$run->id}/email"))->assertSessionHas('success');
    Queue::assertPushed(SendPayslipEmail::class, 1);

    booking(fn () => Payslip::query()->whereKey(prSlipId($run))->update(['emailed_at' => now()]));
    Queue::fake();
    post(tenantUrl('sunrise', "/payroll/runs/{$run->id}/email"))->assertSessionHas('error');
    Queue::assertNothingPushed();
    post(tenantUrl('sunrise', "/payroll/runs/{$run->id}/email"), ['again' => '1'])->assertSessionHas('success');
    Queue::assertPushed(SendPayslipEmail::class, 1);
});

it('sends the payslip email with the PDF and marks it sent', function (): void {
    Notification::fake();
    prMarch();
    $run = prApprove(prRun());
    $id = prSlipId($run);

    booking(fn () => app()->call([new SendPayslipEmail($id), 'handle']));

    Notification::assertSentOnDemandTimes(PayslipMessage::class, 1);
    expect(booking(fn () => Payslip::query()->findOrFail($id)->emailed_at))->not->toBeNull();
});

it('downloads the bank transfer file and the register as CSV, only for an approved run', function (): void {
    prMarch();
    $run = prRun();
    staffUser(DefaultRole::Accountant);

    get(tenantUrl('sunrise', "/payroll/runs/{$run->id}/register?export=csv"))->assertOk();
    get(tenantUrl('sunrise', "/payroll/runs/{$run->id}/bank-file"))->assertSessionHas('error');
    prApprove($run);
    $file = get(tenantUrl('sunrise', "/payroll/runs/{$run->id}/bank-file"))->assertOk();
    expect($file->streamedContent())->toContain('Rahim Uddin')->toContain('1101-555')->toContain('38647.43');
});

it('keeps the screens to the people who may use them', function (): void {
    prMarch();
    $run = prRun();
    staffUser(DefaultRole::FrontDeskAgent);

    get(tenantUrl('sunrise', '/payroll/runs'))->assertForbidden();
    get(tenantUrl('sunrise', "/payroll/runs/{$run->id}"))->assertForbidden();
    get(tenantUrl('sunrise', '/payroll/payslips/'.prSlipId($run)))->assertForbidden();
    get(tenantUrl('sunrise', '/payroll/loans'))->assertForbidden();
    post(tenantUrl('sunrise', '/payroll/runs'), ['month' => '2026-03'])->assertForbidden();
});

it('gives a loan from the screen and shows its schedule', function (): void {
    [$rahim] = prMarch();
    staffUser(DefaultRole::PayrollOfficer);
    Event::fake([LoanDisbursed::class]);

    get(tenantUrl('sunrise', '/payroll/loans/create'))->assertOk();
    post(tenantUrl('sunrise', '/payroll/loans'), ['employee_id' => '', 'principal' => '0', 'installments' => '99'])->assertSessionHasErrors(['employee_id', 'kind', 'principal', 'installments', 'first_month', 'disbursed_on', 'payment_method']);
    post(tenantUrl('sunrise', '/payroll/loans'), ['employee_id' => $rahim->id, 'kind' => 'loan', 'principal' => '10000', 'installments' => 3, 'first_month' => '2026-04', 'disbursed_on' => '2026-03-20', 'payment_method' => 'cash', 'reason' => 'Medical'])->assertSessionHas('success');
    $loan = booking(fn (): Loan => Loan::query()->firstOrFail());

    Event::assertDispatched(LoanDisbursed::class);
    get(tenantUrl('sunrise', '/payroll/loans'))->assertOk()->assertSee($loan->loan_no);
    get(tenantUrl('sunrise', "/payroll/loans/{$loan->id}"))->assertOk()->assertSee('3,333.33')->assertSee('3,333.34');
    post(tenantUrl('sunrise', '/payroll/loans'), ['employee_id' => $rahim->id, 'kind' => 'advance', 'principal' => '1000', 'installments' => 4, 'first_month' => '2026-04', 'disbursed_on' => '2026-03-20', 'payment_method' => 'cash'])->assertSessionHas('error');
});

it('refuses a loan that is too long, starts too early, or is for someone who has left', function (): void {
    [$rahim] = prMarch();

    expect(fn () => prLoan($rahim, '1000', 4, LoanKind::Advance))->toThrow(PayrollRuleViolated::class, '1 to 3')
        ->and(fn () => prLoan($rahim, '1000', 3, LoanKind::Loan, '2026-01-01', '2026-02-20'))->toThrow(PayrollRuleViolated::class, 'before the month')
        ->and(fn () => prLoan($rahim, '0', 3))->toThrow(PayrollRuleViolated::class, 'amount to lend');

    $loan = prLoan($rahim, '10000', 3);
    $rows = booking(fn () => LoanInstallment::query()->where('loan_id', $loan->id)->orderBy('sequence')->get());
    expect($loan->loan_no)->toStartWith('LN-')->and($rows->pluck('amount')->map(fn ($a): string => (string) $a)->all())->toBe(['3333.33', '3333.33', '3333.34'])
        ->and($rows->pluck('due_month')->map(fn ($d): string => $d->format('Y-m'))->all())->toBe(['2026-03', '2026-04', '2026-05']);
});

it('moves an installment to the end of the loan, and writes one off with a reason', function (): void {
    Event::fake([LoanInstallmentWaived::class]);
    [$rahim] = prMarch();
    $loan = prLoan($rahim, '9000', 3);
    $first = booking(fn (): LoanInstallment => LoanInstallment::query()->where('loan_id', $loan->id)->where('sequence', 1)->firstOrFail());

    $skipped = booking(fn (): LoanInstallment => app(SkipInstallment::class)->handle($first));
    expect($skipped->due_month->format('Y-m'))->toBe('2026-06')->and($skipped->sequence)->toBe(4);

    $second = booking(fn (): LoanInstallment => LoanInstallment::query()->where('loan_id', $loan->id)->where('sequence', 2)->firstOrFail());
    expect(fn () => booking(fn () => app(WaiveInstallment::class)->handle($second, ' ')))->toThrow(PayrollRuleViolated::class, 'Say why');
    booking(fn () => app(WaiveInstallment::class)->handle($second, 'Hardship'));
    Event::assertDispatched(LoanInstallmentWaived::class);
    expect(fn () => booking(fn () => app(WaiveInstallment::class)->handle($second, 'Again')))->toThrow(PayrollRuleViolated::class, 'still to be deducted');

    foreach ([3, 4] as $sequence) {
        $row = booking(fn (): LoanInstallment => LoanInstallment::query()->where('loan_id', $loan->id)->where('sequence', $sequence)->firstOrFail());
        booking(fn () => app(WaiveInstallment::class)->handle($row, 'Written off'));
    }
    expect(booking(fn (): LoanStatus => Loan::query()->findOrFail($loan->id)->status))->toBe(LoanStatus::Closed)
        ->and(booking(fn (): int => LoanInstallment::query()->where('loan_id', $loan->id)->where('status', InstallmentStatus::Waived->value)->count()))->toBe(3);
});

it('skips and waives installments from the loan page', function (): void {
    [$rahim] = prMarch();
    $loan = prLoan($rahim, '9000', 3);
    staffUser(DefaultRole::PayrollOfficer);
    $row = booking(fn (): int => LoanInstallment::query()->where('loan_id', $loan->id)->where('sequence', 1)->value('id'));

    post(tenantUrl('sunrise', "/payroll/loans/{$loan->id}/installments/{$row}/skip"))->assertSessionHas('success');
    post(tenantUrl('sunrise', "/payroll/loans/{$loan->id}/installments/{$row}/waive"), ['reason' => ''])->assertSessionHasErrors('reason');
    post(tenantUrl('sunrise', "/payroll/loans/{$loan->id}/installments/{$row}/waive"), ['reason' => 'Hardship'])->assertSessionHas('success');
});
