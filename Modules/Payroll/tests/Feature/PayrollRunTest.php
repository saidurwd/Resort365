<?php

/*
| The payroll run (Step 6.5): opening, calculating from salaries, attendance, overtime, absence, loans and part months,
| adjusting, approving, paying, and the lock. "Done when": a demo month's payroll is calculated, approved and paid, and cannot
| be edited afterwards.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Payroll\Actions\AdjustPayslip;
use Modules\Payroll\Actions\CalculatePayroll;
use Modules\Payroll\Actions\CreatePayrollRun;
use Modules\Payroll\Actions\DeletePayrollRun;
use Modules\Payroll\Actions\PayPayrollRun;
use Modules\Payroll\Actions\RemovePayslipAdjustment;
use Modules\Payroll\Actions\SetEmployeeSalary;
use Modules\Payroll\Actions\SubmitPayrollRun;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\InstallmentStatus;
use Modules\Payroll\Enums\LoanKind;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Events\PayrollApproved;
use Modules\Payroll\Events\PayrollPaid;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstallment;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;

require_once __DIR__.'/../Support/payroll-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    prPrepare();
});

it('opens a run for a property and month, numbered, once, and not for a month that has not begun', function (): void {
    $run = prRun('2026-03', calculate: false);

    expect($run->run_no)->toStartWith('PRUN-')->and($run->status)->toBe(RunStatus::Draft)->and($run->period->toDateString())->toBe('2026-03-01')
        ->and(fn () => prRun('2026-03', calculate: false))->toThrow(PayrollRuleViolated::class, 'already a payroll run')
        ->and(fn () => prRun(now()->addMonths(2)->format('Y-m'), calculate: false))->toThrow(PayrollRuleViolated::class, 'not begun')
        ->and(fn () => booking(fn () => app(CreatePayrollRun::class)->handle(bookingIds()['property'], 'March', [], null, 1)))->toThrow(PayrollRuleViolated::class, 'Choose a month');
});

it('calculates a month from the salary, overtime, absence, a loan installment and a part month (done when)', function (): void {
    [$rahim] = prMarch();
    prLoan($rahim, '6000', 3);
    $run = prRun();
    $a = prSlip($run, 'EMP-0001');
    $b = prSlip($run, 'EMP-0002');

    // Rahim: 25,000 basic. Overtime 2 h × 25,000 ÷ 208 × 2 = 480.77; one missing day costs 42,500 ÷ 30 = 1,416.67; the loan installment 2,000.
    // Taxable pay 42,980.77 a month is still under the 5,000 minimum, so tax stays 416.67. Net 42,980.77 − (2,500 + 2,000 + 1,416.67) − 416.67.
    expect($run->status)->toBe(RunStatus::Calculated)->and($run->employees_count)->toBe(2)->and($run->calculated_at)->not->toBeNull()
        ->and(prLines($a))->toEqual(['BASIC' => '25000.00', 'HRENT' => '12500.00', 'MEDICAL' => '2500.00', 'CONV' => '2500.00', 'OT' => '480.77', 'LOAN' => '2000.00', 'PF_EMP' => '2500.00', 'PF_ER' => '2500.00', 'GRAT' => '2083.33', 'TAX' => '416.67', 'ABSENCE' => '1416.67'])
        ->and((string) $a->gross)->toBe('42980.77')->and((string) $a->deductions)->toBe('5916.67')->and((string) $a->tax)->toBe('416.67')->and((string) $a->net)->toBe('36647.43')->and($a->overtime_minutes)->toBe(120)
        ->and((string) $a->absent_days)->toBe('1.0')->and($a->installment_ids)->toHaveCount(1)->and($a->employee_email)->toBe('rahim@example.test')->and($a->department_name)->not->toBeNull()
        // New joined on the 16th: 16 of 31 days, every regular line and the tax scaled by 0.5161.
        ->and($b->days_employed)->toBe(16)->and($b->days_in_month)->toBe(31)->and((string) $b->pay_fraction)->toBe('0.5161')->and(prLines($b)['BASIC'])->toBe('10322.00')->and((string) $b->gross)->toBe('17805.45')->and((string) $b->net)->toBe('16773.25')
        ->and((string) $run->gross_total)->toBe('60786.22')->and((string) $run->deductions_total)->toBe('6948.87')->and((string) $run->tax_total)->toBe('416.67')->and((string) $run->net_total)->toBe('53420.68')
        ->and($run->warnings)->toHaveCount(1)->and($run->warnings[0])->toContain('Zed')->toContain('no salary');
});

it('pays an annual component with the run when it is included, and not otherwise', function (): void {
    [$rahim] = prMarch();
    $with = booking(fn (): PayrollRun => app(CalculatePayroll::class)->handle(booking(fn (): PayrollRun => app(CreatePayrollRun::class)->handle(bookingIds()['property'], '2026-03', ['fbonus'], null, 1))));

    expect(prLines(prSlip($with, 'EMP-0001'))['FBONUS'])->toBe('25000.00')->and((string) prSlip($with, 'EMP-0001')->gross)->toBe('67980.77')->and($with->include_annual)->toBe(['FBONUS']);
});

it('calculates again, replacing the payslips, until the run goes for approval', function (): void {
    prMarch();
    $run = prRun();
    $slip = prSlip($run, 'EMP-0001');
    booking(fn () => app(AdjustPayslip::class)->handle($slip, ComponentKind::Earning, 'Bonus', '1000', 'Good month'));
    expect((string) prFresh($run)->gross_total)->toBe('61786.22');

    $again = booking(fn (): PayrollRun => app(CalculatePayroll::class)->handle(prFresh($run)));
    expect((string) $again->gross_total)->toBe('60786.22')->and(booking(fn (): int => Payslip::query()->count()))->toBe(2)->and(booking(fn (): int => PayslipLine::query()->where('code', 'ADJ')->count()))->toBe(0);
});

it('keeps the tax and moves the net when a payslip is adjusted', function (): void {
    prMarch();
    $run = prRun();
    $bonus = booking(fn (): PayslipLine => app(AdjustPayslip::class)->handle(prSlip($run, 'EMP-0001'), ComponentKind::Earning, 'Performance bonus', '1500.50', 'Beat the target'));
    booking(fn () => app(AdjustPayslip::class)->handle(prSlip($run, 'EMP-0001'), ComponentKind::Deduction, 'Uniform fine', '300', 'Lost uniform'));
    $after = prSlip($run, 'EMP-0001');

    expect((string) $after->gross)->toBe('44481.27')->and((string) $after->deductions)->toBe('4216.67')->and((string) $after->tax)->toBe('416.67')->and((string) $after->net)->toBe('39847.93')
        ->and((string) prFresh($run)->net_total)->toBe('56621.18');

    booking(fn () => app(RemovePayslipAdjustment::class)->handle($bonus));
    expect((string) prSlip($run, 'EMP-0001')->net)->toBe('38347.43')
        ->and(fn () => booking(fn () => app(RemovePayslipAdjustment::class)->handle(prSlip($run, 'EMP-0001')->lines->firstWhere('code', 'BASIC'))))->toThrow(PayrollRuleViolated::class, 'Only an adjustment')
        ->and(fn () => booking(fn () => app(AdjustPayslip::class)->handle(prSlip($run, 'EMP-0001'), ComponentKind::Earning, 'x', '0', 'y')))->toThrow(PayrollRuleViolated::class, 'more than zero')
        ->and(fn () => booking(fn () => app(AdjustPayslip::class)->handle(prSlip($run, 'EMP-0001'), ComponentKind::Earning, 'x', '5', ' ')))->toThrow(PayrollRuleViolated::class, 'say why')
        ->and(fn () => booking(fn () => app(AdjustPayslip::class)->handle(prSlip($run, 'EMP-0001'), ComponentKind::Employer, 'x', '5', 'y')))->toThrow(PayrollRuleViolated::class, 'earning or a deduction');
});

it('sends the run for approval, locks it, and approves it: the installments are deducted, the run cannot be changed (done when)', function (): void {
    Event::fake([PayrollApproved::class, PayrollPaid::class]);
    [$rahim] = prMarch();
    $loan = prLoan($rahim, '6000', 3);
    $run = prRun();
    $submitted = booking(fn (): PayrollRun => app(SubmitPayrollRun::class)->handle(prFresh($run), prUser()->id));
    $installments = fn () => booking(fn () => LoanInstallment::query()->where('loan_id', $loan->id)->orderBy('sequence')->get()->map(fn (LoanInstallment $i): string => $i->status->value)->all());

    // Waiting for approval: locked, the installments not yet deducted.
    expect($submitted->status)->toBe(RunStatus::Pending)->and($submitted->approval_request_id)->not->toBeNull()->and($installments())->toBe(['scheduled', 'scheduled', 'scheduled'])
        ->and(fn () => booking(fn () => app(AdjustPayslip::class)->handle(prSlip($run, 'EMP-0001'), ComponentKind::Earning, 'Bonus', '100', 'x')))->toThrow(PayrollRuleViolated::class, 'waiting for approval')
        ->and(fn () => booking(fn () => app(CalculatePayroll::class)->handle(prFresh($run))))->toThrow(PayrollRuleViolated::class, 'cannot be calculated again')
        ->and(fn () => booking(fn () => app(DeletePayrollRun::class)->handle(prFresh($run))))->toThrow(PayrollRuleViolated::class, 'cannot be deleted');

    booking(fn () => app(ApprovalEngine::class)->approve((int) $submitted->approval_request_id, prUser(DefaultRole::Accountant)->id));
    $approved = prFresh($run);

    expect($approved->status)->toBe(RunStatus::Approved)->and($approved->approved_at)->not->toBeNull()->and($installments())->toBe(['deducted', 'scheduled', 'scheduled'])
        ->and(booking(fn (): LoanStatus => Loan::query()->findOrFail($loan->id)->status))->toBe(LoanStatus::Active);
    Event::assertDispatched(PayrollApproved::class, fn (PayrollApproved $event): bool => $event->runId === $run->id);

    // Locked, even from code.
    $slip = prSlip($run, 'EMP-0001');
    expect(fn () => booking(fn () => $slip->update(['net' => '1.00'])))->toThrow(PayrollRuleViolated::class, 'cannot be changed')
        ->and(fn () => booking(fn () => $slip->lines->first()->update(['amount' => '1.00'])))->toThrow(PayrollRuleViolated::class, 'cannot be changed')
        ->and(fn () => booking(fn () => $slip->lines->first()->delete()))->toThrow(PayrollRuleViolated::class, 'cannot be changed')
        ->and(fn () => booking(fn () => $slip->delete()))->toThrow(PayrollRuleViolated::class, 'cannot be changed')
        ->and(fn () => booking(fn () => $approved->update(['net_total' => '1.00'])))->toThrow(PayrollRuleViolated::class, 'cannot be changed')
        ->and(fn () => booking(fn () => $approved->delete()))->toThrow(PayrollRuleViolated::class, 'cannot be deleted')
        ->and(fn () => booking(fn () => app(RemovePayslipAdjustment::class)->handle($slip->lines->first())))->toThrow(PayrollRuleViolated::class);
});

it('opens the run again when the approval is rejected, and refuses to send a run with a negative net pay', function (): void {
    prMarch();
    $run = prRun();
    booking(fn () => app(AdjustPayslip::class)->handle(prSlip($run, 'EMP-0002'), ComponentKind::Deduction, 'Fine', '30000', 'Test'));
    expect(fn () => booking(fn () => app(SubmitPayrollRun::class)->handle(prFresh($run), 1)))->toThrow(PayrollRuleViolated::class, 'negative net pay');

    booking(fn () => app(RemovePayslipAdjustment::class)->handle(prSlip($run, 'EMP-0002')->lines->firstWhere('code', 'ADJ')));
    $submitted = booking(fn (): PayrollRun => app(SubmitPayrollRun::class)->handle(prFresh($run), prUser()->id));
    booking(fn () => app(ApprovalEngine::class)->reject((int) $submitted->approval_request_id, prUser(DefaultRole::Accountant)->id, 'Check the overtime'));

    expect(prFresh($run)->status)->toBe(RunStatus::Calculated)->and(booking(fn (): PayrollRun => app(CalculatePayroll::class)->handle(prFresh($run)))->status)->toBe(RunStatus::Calculated);
});

it('needs the General Manager as well for a large run', function (): void {
    [$rahim] = prMarch();
    booking(fn () => app(SetEmployeeSalary::class)->handle($rahim->id, ['salary_structure_id' => pyStructure()->id, 'effective_from' => '2026-03-01', 'basic' => '2500000', 'tax_category' => 'general', 'tax_area' => 'metro'], 1));
    $run = prRun();
    $submitted = booking(fn (): PayrollRun => app(SubmitPayrollRun::class)->handle(prFresh($run), prUser()->id));
    booking(fn () => app(ApprovalEngine::class)->approve((int) $submitted->approval_request_id, prUser(DefaultRole::Accountant)->id));

    expect(prFresh($run)->status)->toBe(RunStatus::Pending);
    booking(fn () => app(ApprovalEngine::class)->approve((int) $submitted->approval_request_id, prUser(DefaultRole::GeneralManager)->id));
    expect(prFresh($run)->status)->toBe(RunStatus::Approved);
});

it('records the payment of an approved run, once, and then it is final', function (): void {
    Event::fake([PayrollPaid::class]);
    prMarch();
    $run = prRun();
    $pay = fn (string $date = '2026-10-01', PayMethod $method = PayMethod::BankTransfer) => booking(fn (): PayrollRun => app(PayPayrollRun::class)->handle(prFresh($run), $method, $date, 'BEFTN-77', 1));

    expect(fn () => $pay())->toThrow(PayrollRuleViolated::class, 'only an approved run is paid');
    prApprove($run);
    $today = now()->toDateString();

    expect(fn () => $pay(now()->addDay()->toDateString()))->toThrow(PayrollRuleViolated::class, 'future');
    $paid = $pay($today);
    expect($paid->status)->toBe(RunStatus::Paid)->and($paid->paid_on?->toDateString())->toBe($today)->and($paid->payment_method)->toBe(PayMethod::BankTransfer)->and($paid->payment_reference)->toBe('BEFTN-77')
        ->and(fn () => $pay($today))->toThrow(PayrollRuleViolated::class, 'only an approved run is paid')
        ->and(fn () => booking(fn () => prFresh($run)->update(['net_total' => '1'])))->toThrow(PayrollRuleViolated::class, 'cannot be changed');
    Event::assertDispatched(PayrollPaid::class, fn (PayrollPaid $event): bool => $event->runId === $run->id);
});

it('deletes a run that has not gone for approval, with its payslips', function (): void {
    prMarch();
    $run = prRun();
    booking(fn () => app(DeletePayrollRun::class)->handle(prFresh($run)));

    expect(booking(fn (): int => PayrollRun::query()->count()))->toBe(0)->and(booking(fn (): int => Payslip::query()->count()))->toBe(0)->and(booking(fn (): int => PayslipLine::query()->count()))->toBe(0);
});

it('closes a loan once its last installment is deducted by an approved run', function (): void {
    [$rahim] = prMarch();
    $advance = prLoan($rahim, '1500', 1, LoanKind::Advance);
    prApprove(prRun());

    expect(booking(fn (): LoanStatus => Loan::query()->findOrFail($advance->id)->status))->toBe(LoanStatus::Closed)->and(booking(fn (): string => LoanInstallment::query()->where('loan_id', $advance->id)->sole()->status->value))->toBe(InstallmentStatus::Deducted->value);
});
