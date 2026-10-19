<?php

/*
| Salary and income tax arithmetic (Step 6.4), with worked examples for the Bangladesh starter structure. "Done when": an
| employee's monthly gross, deductions and tax are shown correctly for the configured structure.
*/
use Modules\Payroll\DTOs\StructureLine;
use Modules\Payroll\Enums\CalcType;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Services\Calculation\FormulaEvaluator;
use Modules\Payroll\Services\Calculation\IncomeTaxCalculator;
use Modules\Payroll\Services\Calculation\SalaryCalculator;

require_once __DIR__.'/../Support/payroll-fixtures.php';

it('works out basic 25,000: gross 42,500, provident fund 2,500, the minimum tax of 5,000 a year, net 39,583.33', function (): void {
    $pay = (new SalaryCalculator)->calculate(pxStarterLines(), '25000', pxTaxTable(), 'general', 'metro');

    // Monthly: 25,000 + 12,500 + 2,500 + 2,500 = 42,500. Year: 12 × 42,500 + two bonuses of 25,000 = 560,000 of salary income.
    // One third is exempt (186,666.67), leaving 373,333.33: only 23,333.33 over the 350,000 tax-free amount, so 1,166.67 of tax,
    // which is under the 5,000 minimum for a city corporation resident; 5,000 a year is 416.67 a month.
    expect($pay->gross)->toBe('42500.00')->and($pay->deductions)->toBe('2500.00')->and($pay->tax)->toBe('416.67')->and($pay->net)->toBe('39583.33')->and($pay->employerCost)->toBe('4583.33')
        ->and($pay->annualIncome)->toBe('560000.00')->and($pay->taxDetails?->exemption)->toBe('186666.67')->and($pay->taxDetails?->taxableIncome)->toBe('373333.33')
        ->and($pay->taxDetails?->tax)->toBe('5000.00')->and($pay->taxDetails?->minimumApplied)->toBeTrue()
        ->and(pxAmounts($pay))->toBe(['BASIC' => '25000.00', 'HRENT' => '12500.00', 'MEDICAL' => '2500.00', 'CONV' => '2500.00', 'FBONUS' => '25000.00', 'PF_EMP' => '2500.00', 'PF_ER' => '2500.00', 'GRAT' => '2083.33', 'TAX' => '416.67']);
});

it('works out basic 100,000: gross 162,500, tax 207,500 a year across five bands, 17,291.67 a month', function (): void {
    $pay = (new SalaryCalculator)->calculate(pxStarterLines(), '100000', pxTaxTable(), 'general', 'metro');

    // Year: 12 × 162,500 + 200,000 = 2,150,000. The exemption stops at its 450,000 cap, so 1,700,000 is taxable; over the 350,000
    // free amount: 100,000 × 5% + 300,000 × 10% + 400,000 × 15% + 500,000 × 20% + 50,000 × 25% = 207,500.
    expect($pay->gross)->toBe('162500.00')->and($pay->deductions)->toBe('10000.00')->and($pay->tax)->toBe('17291.67')->and($pay->net)->toBe('135208.33')->and($pay->annualIncome)->toBe('2150000.00')
        ->and($pay->taxDetails?->exemption)->toBe('450000.00')->and($pay->taxDetails?->taxableIncome)->toBe('1700000.00')->and($pay->taxDetails?->tax)->toBe('207500.00')->and($pay->taxDetails?->minimumApplied)->toBeFalse();
});

it('gives a woman or senior taxpayer the higher tax-free amount', function (): void {
    $pay = (new SalaryCalculator)->calculate(pxStarterLines(), '100000', pxTaxTable(), 'women_senior', 'metro');

    // 400,000 free instead of 350,000: 1,300,000 above it is taxed 5,000 + 30,000 + 60,000 + 100,000 = 195,000, or 16,250 a month.
    expect($pay->taxDetails?->tax)->toBe('195000.00')->and($pay->tax)->toBe('16250.00')->and($pay->net)->toBe('136250.00');
});

it('charges no tax under the tax-free amount, and no minimum either', function (): void {
    $pay = (new SalaryCalculator)->calculate(pxStarterLines(), '15000', pxTaxTable(), 'general', 'metro');

    // Monthly 15,000 + 7,500 + 1,500 + 2,500 = 26,500; year 318,000 + 30,000 = 348,000; taxable 232,000 is under 350,000.
    expect($pay->gross)->toBe('26500.00')->and($pay->taxDetails?->taxableIncome)->toBe('232000.00')->and($pay->tax)->toBe('0.00')->and($pay->taxDetails?->minimumApplied)->toBeFalse()->and($pay->net)->toBe('25000.00');
});

it('uses the minimum for the taxpayer\'s area', function (): void {
    $city = (new SalaryCalculator)->calculate(pxStarterLines(), '25000', pxTaxTable(), 'general', 'city');
    $elsewhere = (new SalaryCalculator)->calculate(pxStarterLines(), '25000', pxTaxTable(), 'general', 'other');

    expect($city->taxDetails?->tax)->toBe('4000.00')->and($city->tax)->toBe('333.33')->and($elsewhere->taxDetails?->tax)->toBe('3000.00')->and($elsewhere->tax)->toBe('250.00');
});

it('takes an amount set for the employee, and withholds no tax when there is no tax table or no tax line', function (): void {
    $set = (new SalaryCalculator)->calculate(pxStarterLines(), '25000', pxTaxTable(), 'general', 'metro', ['CONV' => '3000']);
    $noTable = (new SalaryCalculator)->calculate(pxStarterLines(), '25000', null);
    $noTaxLine = (new SalaryCalculator)->calculate(array_values(array_filter(pxStarterLines(), fn (StructureLine $line): bool => $line->code !== 'TAX')), '25000', pxTaxTable());

    expect($set->gross)->toBe('43000.00')->and(pxAmounts($set)['CONV'])->toBe('3000.00')->and($noTable->tax)->toBe('0.00')->and($noTable->net)->toBe('40000.00')->and($noTaxLine->tax)->toBe('0.00')->and($noTaxLine->taxDetails)->toBeNull();
});

it('resolves lines whatever their order, and percentages of percentages', function (): void {
    $lines = [
        new StructureLine('BONUS', 'Bonus', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, CalcType::Percent, percent: '10', percentOf: 'ALLOW'),
        new StructureLine('ALLOW', 'Allowance', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, CalcType::Formula, formula: 'MIN(BASIC * 0.4, 5000)'),
        new StructureLine('BASIC', 'Basic', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, CalcType::Fixed),
    ];

    expect(pxAmounts((new SalaryCalculator)->calculate($lines, '20000', null)))->toBe(['BONUS' => '500.00', 'ALLOW' => '5000.00', 'BASIC' => '20000.00'])
        ->and((new SalaryCalculator)->calculate($lines, '20000', null)->gross)->toBe('25500.00');
});

it('rounds each component half-up to the cent', function (): void {
    $lines = [
        new StructureLine('BASIC', 'Basic', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, CalcType::Fixed),
        new StructureLine('ODD', 'Odd', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, CalcType::Percent, percent: '33.33', percentOf: 'BASIC'),
    ];

    expect(pxAmounts((new SalaryCalculator)->calculate($lines, '1000.50', null))['ODD'])->toBe('333.47');
});

it('evaluates formulas with precedence, brackets, unary minus and the functions', function (): void {
    $formula = new FormulaEvaluator;
    $values = ['BASIC' => '20000', 'HRENT' => '10000'];

    expect($formula->evaluate('1 + 2 * 3', []))->toBe('7.0000000000')->and($formula->evaluate('(1 + 2) * 3', []))->toBe('9.0000000000')->and($formula->evaluate('-BASIC + 25000', $values))->toBe('5000.0000000000')
        ->and($formula->evaluate('basic * 0.5', $values))->toBe('10000.0000000000')->and($formula->evaluate('MIN(BASIC * 0.1, 1500)', $values))->toBe('1500.0000000000')
        ->and($formula->evaluate('MAX(BASIC, HRENT, 30000)', $values))->toBe('30000.0000000000')->and($formula->evaluate('ROUND(10 / 3, 2)', []))->toBe('3.3300000000')
        ->and($formula->evaluate('ABS(HRENT - BASIC)', $values))->toBe('10000.0000000000')->and($formula->evaluate('BASIC / 3', $values))->toBe('6666.6666666667')
        ->and($formula->references('MIN(BASIC * 0.1, HRENT) + basic'))->toBe(['BASIC', 'HRENT']);
});

it('splits the starter structure\'s annual components from the monthly ones, and keeps the gratuity provision out of net pay', function (): void {
    $pay = (new SalaryCalculator)->calculate(pxStarterLines(), '30000', pxTaxTable(), 'general', 'other');
    $bonus = collect($pay->lines)->firstWhere('code', 'FBONUS');

    expect($bonus?->frequency)->toBe(ComponentFrequency::Annual)->and($bonus?->annualTimes)->toBe(2)->and($bonus?->amount)->toBe('30000.00')->and($pay->gross)->toBe('50500.00')
        ->and($pay->net)->toBe(number_format(50500 - 3000 - (float) $pay->tax, 2, '.', ''))->and($pay->employerCost)->toBe('5500.00');
});

it('taxes the slab edges exactly', function (): void {
    $tax = new IncomeTaxCalculator;
    $table = pxTaxTable();
    // Income whose one-third exemption stays under the cap: exemption = income / 3.
    $edge = $tax->calculate('525000', $table, 'general', 'other');     // exemption 175,000 → taxable exactly 350,000
    $above = $tax->calculate('525150', $table, 'general', 'other');    // taxable 350,100 → 100 × 5% = 5, under the minimum

    expect($edge->taxableIncome)->toBe('350000.00')->and($edge->tax)->toBe('0.00')->and($edge->minimumApplied)->toBeFalse()->and($above->tax)->toBe('3000.00')->and($above->minimumApplied)->toBeTrue()
        ->and($tax->calculate('0', $table, 'general', 'other')->tax)->toBe('0.00')->and($tax->calculate('3000000', $table, 'disabled', 'other')->exemption)->toBe('450000.00');
});
