<?php

use Modules\Payroll\DTOs\SalaryBreakdown;
use Modules\Payroll\DTOs\StructureLine;
use Modules\Payroll\DTOs\TaxTableData;
use Modules\Payroll\Enums\CalcType;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;

/**
 * The Bangladesh starter structure: basic; house rent 50% and medical 10% of basic; conveyance set per employee (2,500 by
 * default); two festival bonuses of 100% of basic; provident fund 10% from the employee and the employer; gratuity provision
 * a twelfth of basic; income tax withheld.
 *
 * @return list<StructureLine>
 */
function pxStarterLines(): array
{
    return [
        new StructureLine('BASIC', 'Basic salary', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, CalcType::Fixed),
        new StructureLine('HRENT', 'House rent', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, CalcType::Percent, percent: '50', percentOf: 'BASIC'),
        new StructureLine('MEDICAL', 'Medical allowance', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, CalcType::Percent, percent: '10', percentOf: 'BASIC'),
        new StructureLine('CONV', 'Conveyance', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, CalcType::Employee, amount: '2500'),
        new StructureLine('FBONUS', 'Festival bonus', ComponentKind::Earning, true, ComponentFrequency::Annual, 2, CalcType::Percent, percent: '100', percentOf: 'BASIC'),
        new StructureLine('PF_EMP', 'Provident fund (employee)', ComponentKind::Deduction, false, ComponentFrequency::Monthly, 1, CalcType::Percent, percent: '10', percentOf: 'BASIC'),
        new StructureLine('PF_ER', 'Provident fund (employer)', ComponentKind::Employer, false, ComponentFrequency::Monthly, 1, CalcType::Percent, percent: '10', percentOf: 'BASIC'),
        new StructureLine('GRAT', 'Gratuity provision', ComponentKind::Employer, false, ComponentFrequency::Monthly, 1, CalcType::Formula, formula: 'BASIC / 12'),
        new StructureLine('TAX', 'Income tax', ComponentKind::Deduction, false, ComponentFrequency::Monthly, 1, CalcType::Fixed),
    ];
}

/** Bangladesh income tax for 2025-26 as the starter pack seeds it. */
function pxTaxTable(): TaxTableData
{
    return new TaxTableData(
        ['general' => '350000', 'women_senior' => '400000', 'disabled' => '475000', 'freedom_fighter' => '500000'],
        ['metro' => '5000', 'city' => '4000', 'other' => '3000'],
        '1/3', '450000',
        [['width' => '100000', 'rate' => '5'], ['width' => '300000', 'rate' => '10'], ['width' => '400000', 'rate' => '15'], ['width' => '500000', 'rate' => '20'], ['width' => null, 'rate' => '25']],
    );
}

/**
 * A breakdown's amounts by component code.
 *
 * @return array<string, string>
 */
function pxAmounts(SalaryBreakdown $breakdown): array
{
    $found = [];

    foreach ($breakdown->lines as $line) {
        $found[$line->code] = $line->amount;
    }

    return $found;
}
