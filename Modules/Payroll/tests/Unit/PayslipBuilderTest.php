<?php

/*
| The payroll month (Step 6.5): overtime, absence and unpaid leave, loan installments, festival bonuses and part months, with
| worked examples on the Bangladesh starter structure; and the installment schedule of a loan.
*/
use Modules\Payroll\DTOs\PayrollRules;
use Modules\Payroll\Services\Calculation\LoanSchedule;
use Modules\Payroll\Services\Calculation\PayslipBuilder;

require_once __DIR__.'/../Support/payroll-fixtures.php';

it('works out an ordinary month: the structure, no festival bonus, income tax a twelfth of the year', function (): void {
    $slip = (new PayslipBuilder)->build(pxInput('25000'));

    expect(pxPayslip($slip))->toBe(['BASIC' => '25000.00', 'HRENT' => '12500.00', 'MEDICAL' => '2500.00', 'CONV' => '2500.00', 'PF_EMP' => '2500.00', 'PF_ER' => '2500.00', 'GRAT' => '2083.33'])
        ->and($slip->gross)->toBe('42500.00')->and($slip->deductions)->toBe('2500.00')->and($slip->tax)->toBe('416.67')->and($slip->net)->toBe('39583.33')->and($slip->employerCost)->toBe('4583.33');
});

it('pays overtime at twice the hourly basic rate, and withholds tax on it', function (): void {
    // 10 hours × (25,000 ÷ 208) × 2 = 2,403.85. At this salary the tax is still the minimum.
    $small = (new PayslipBuilder)->build(pxInput('25000', ['overtimeMinutes' => 600]));
    // At 100,000: 10 × (100,000 ÷ 208) × 2 = 9,615.38; yearly taxable pay 2,265,384.56 → tax 236,346.14 → 19,695.51 a month.
    $large = (new PayslipBuilder)->build(pxInput('100000', ['overtimeMinutes' => 600]));

    expect(pxPayslip($small)['OT'])->toBe('2403.85')->and($small->gross)->toBe('44903.85')->and($small->tax)->toBe('416.67')->and($small->net)->toBe('41987.18')
        ->and(pxPayslip($large)['OT'])->toBe('9615.38')->and($large->gross)->toBe('172115.38')->and($large->tax)->toBe('19695.51')->and($large->net)->toBe('142419.87');
});

it('uses the tenant\'s overtime settings', function (): void {
    $rules = new PayrollRules(overtimeMultiplier: '1.5', overtimeHoursPerMonth: '200', salaryDaysBasis: 26);

    expect(pxPayslip((new PayslipBuilder)->build(pxInput('26000', ['overtimeMinutes' => 120, 'rules' => $rules])))['OT'])->toBe('390.00');
});

it('deducts absent days and days of unpaid leave at the regular gross over thirty', function (): void {
    // Regular gross 42,500 ÷ 30 = 1,416.67 a day × (2 absent + 1 unpaid leave) = 4,250.00.
    $slip = (new PayslipBuilder)->build(pxInput('25000', ['absentDays' => '2.0', 'unpaidLeaveDays' => '1.0']));

    expect(pxPayslip($slip)['ABSENCE'])->toBe('4250.00')->and($slip->deductions)->toBe('6750.00')->and($slip->net)->toBe('35333.33')
        ->and(pxPayslip((new PayslipBuilder)->build(pxInput('25000', ['absentDays' => '0.5'])))['ABSENCE'])->toBe('708.33')
        ->and(pxPayslip((new PayslipBuilder)->build(pxInput('25000', ['absentDays' => '45'])))['ABSENCE'])->toBe('42500.00');
});

it('deducts the loan and advance installments due', function (): void {
    $slip = (new PayslipBuilder)->build(pxInput('25000', ['loanDue' => '5000', 'advanceDue' => '2000']));

    expect(pxPayslip($slip)['LOAN'])->toBe('5000.00')->and(pxPayslip($slip)['ADVANCE'])->toBe('2000.00')->and($slip->deductions)->toBe('9500.00')->and($slip->net)->toBe('32583.33');
});

it('pays the festival bonus once in full when the run includes it, with no extra tax', function (): void {
    $slip = (new PayslipBuilder)->build(pxInput('25000', ['includeAnnual' => ['FBONUS']]));

    expect(pxPayslip($slip)['FBONUS'])->toBe('25000.00')->and($slip->gross)->toBe('67500.00')->and($slip->tax)->toBe('416.67')->and($slip->net)->toBe('64583.33');
});

it('scales a joiner\'s or leaver\'s month by the share worked, tax included, but not the overtime or loan', function (): void {
    // Half the month: every regular line and the tax are halved (the tax: 416.67 × 0.5 = 208.34).
    $slip = (new PayslipBuilder)->build(pxInput('25000', ['fraction' => '0.5', 'overtimeMinutes' => 600, 'loanDue' => '1000']));

    expect(pxPayslip($slip)['BASIC'])->toBe('12500.00')->and(pxPayslip($slip)['HRENT'])->toBe('6250.00')->and(pxPayslip($slip)['PF_EMP'])->toBe('1250.00')->and(pxPayslip($slip)['OT'])->toBe('2403.85')
        ->and(pxPayslip($slip)['LOAN'])->toBe('1000.00')->and($slip->gross)->toBe('23653.85')->and($slip->tax)->toBe('208.34');
});

it('works out the absence of a part month from what was actually earned', function (): void {
    // Half a month: regular gross 21,250 ÷ 30 × 1 day = 708.33.
    expect(pxPayslip((new PayslipBuilder)->build(pxInput('25000', ['fraction' => '0.5', 'absentDays' => '1.0'])))['ABSENCE'])->toBe('708.33');
});

it('splits a loan into equal installments, one a month, the last taking the rounding', function (): void {
    $even = LoanSchedule::build('60000', 6, '2026-11-17');
    $odd = LoanSchedule::build('100000', 3, '2026-12-01');

    expect(array_column($even, 'amount'))->toBe(array_fill(0, 6, '10000.00'))->and($even[0]['due_month'])->toBe('2026-11-01')->and($even[5]['due_month'])->toBe('2027-04-01')->and($even[2]['sequence'])->toBe(3)
        ->and(array_column($odd, 'amount'))->toBe(['33333.33', '33333.33', '33333.34'])->and($odd[2]['due_month'])->toBe('2027-02-01')
        ->and(LoanSchedule::build('5000', 1, '2026-12-15'))->toBe([['sequence' => 1, 'due_month' => '2026-12-01', 'amount' => '5000.00']]);
});
