<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Enums\CalcType;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\SalaryStructureComponent;
use Modules\Payroll\Models\TaxSlab;
use Modules\Payroll\Models\TaxTable;

/**
 * The Bangladesh starter pack (Step 6.4): salary components, two salary structures and the 2025-26 income tax table. Everything
 * is data a tenant can change, because the figures change with each budget. Components, structures and tax years the tenant
 * already has are left alone.
 *
 * - Provident fund: 10% of basic from the employee and from the employer.
 * - Gratuity: a provision of one month's basic a year (a twelfth of basic each month).
 * - Festival bonuses: two a year, each 100% of basic, taxable.
 * - Income tax: tax-free 350,000 a year (women and people over 65: 400,000; disabled: 475,000; freedom fighters: 500,000), then
 *   5%, 10%, 15%, 20% and 25% bands; a third of salary up to 450,000 is exempt; minimum tax 5,000 / 4,000 / 3,000 by area.
 */
class SeedBangladeshPayroll extends Action
{
    /**
     * code => [name, kind, taxable, frequency, annual times, system, sort].
     *
     * @var array<string, array{0: string, 1: ComponentKind, 2: bool, 3: ComponentFrequency, 4: int, 5: bool, 6: int}>
     */
    public const array COMPONENTS = [
        'BASIC' => ['Basic salary', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, true, 10],
        'HRENT' => ['House rent', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, false, 20],
        'MEDICAL' => ['Medical allowance', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, false, 30],
        'CONV' => ['Conveyance allowance', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, false, 40],
        'FBONUS' => ['Festival bonus', ComponentKind::Earning, true, ComponentFrequency::Annual, 2, false, 50],
        'OT' => ['Overtime', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, true, 60],
        'SCSHARE' => ['Service charge share', ComponentKind::Earning, true, ComponentFrequency::Monthly, 1, true, 70],
        'PF_EMP' => ['Provident fund (employee)', ComponentKind::Deduction, false, ComponentFrequency::Monthly, 1, false, 110],
        'TAX' => ['Income tax', ComponentKind::Deduction, false, ComponentFrequency::Monthly, 1, true, 120],
        'LOAN' => ['Loan instalment', ComponentKind::Deduction, false, ComponentFrequency::Monthly, 1, true, 130],
        'ADVANCE' => ['Salary advance', ComponentKind::Deduction, false, ComponentFrequency::Monthly, 1, true, 140],
        'ABSENCE' => ['Absence deduction', ComponentKind::Deduction, false, ComponentFrequency::Monthly, 1, true, 150],
        'PF_ER' => ['Provident fund (employer)', ComponentKind::Employer, false, ComponentFrequency::Monthly, 1, false, 210],
        'GRAT' => ['Gratuity provision', ComponentKind::Employer, false, ComponentFrequency::Monthly, 1, false, 220],
    ];

    /**
     * structure code => [name, description, lines: component code => [calc type, amount, percent, percent of, formula]].
     *
     * @var array<string, array{0: string, 1: string, 2: array<string, array{0: CalcType, 1: string|null, 2: string|null, 3: string|null, 4: string|null}>}>
     */
    public const array STRUCTURES = [
        'STD' => ['Standard monthly staff', 'Basic with house rent, medical and conveyance, festival bonuses, provident fund and gratuity.', [
            'BASIC' => [CalcType::Fixed, null, null, null, null],
            'HRENT' => [CalcType::Percent, null, '50', 'BASIC', null],
            'MEDICAL' => [CalcType::Percent, null, '10', 'BASIC', null],
            'CONV' => [CalcType::Employee, '2500', null, null, null],
            'FBONUS' => [CalcType::Percent, null, '100', 'BASIC', null],
            'PF_EMP' => [CalcType::Percent, null, '10', 'BASIC', null],
            'TAX' => [CalcType::Fixed, null, null, null, null],
            'PF_ER' => [CalcType::Percent, null, '10', 'BASIC', null],
            'GRAT' => [CalcType::Formula, null, null, null, 'BASIC / 12'],
        ]],
        'CASUAL' => ['Casual and seasonal (no benefits)', 'Basic salary and income tax only.', [
            'BASIC' => [CalcType::Fixed, null, null, null, null],
            'TAX' => [CalcType::Fixed, null, null, null, null],
        ]],
    ];

    /**
     * @return array{components: int, structures: int, tax_tables: int} what was created
     */
    public function handle(): array
    {
        return $this->transaction(function (): array {
            $made = ['components' => 0, 'structures' => 0, 'tax_tables' => 0];
            $ids = [];

            foreach (self::COMPONENTS as $code => [$name, $kind, $taxable, $frequency, $times, $system, $sort]) {
                $existing = SalaryComponent::query()->withTrashed()->where('code', $code)->first();

                if ($existing instanceof SalaryComponent) {
                    $ids[$code] = $existing->id;

                    continue;
                }

                $ids[$code] = SalaryComponent::query()->create(['code' => $code, 'name' => $name, 'kind' => $kind, 'is_taxable' => $taxable, 'frequency' => $frequency, 'annual_times' => $times, 'is_system' => $system, 'sort_order' => $sort, 'is_active' => true])->id;
                $made['components']++;
            }

            foreach (self::STRUCTURES as $code => [$name, $description, $lines]) {
                if (SalaryStructure::query()->withTrashed()->where('code', $code)->exists()) {
                    continue;
                }

                $structure = SalaryStructure::query()->create(['code' => $code, 'name' => $name, 'description' => $description, 'is_active' => true]);
                $order = 0;

                foreach ($lines as $componentCode => [$type, $amount, $percent, $of, $formula]) {
                    SalaryStructureComponent::query()->create(['salary_structure_id' => $structure->id, 'salary_component_id' => $ids[$componentCode], 'calc_type' => $type, 'amount' => $amount, 'percent' => $percent, 'percent_of' => $of, 'formula' => $formula, 'sort_order' => ++$order * 10]);
                }

                $made['structures']++;
            }

            if (! TaxTable::query()->where('tax_year', '2025-26')->exists()) {
                $table = TaxTable::query()->create([
                    'name' => 'Income tax 2025-26', 'tax_year' => '2025-26', 'effective_from' => '2025-07-01',
                    'thresholds' => [
                        ['code' => 'general', 'label' => 'General taxpayer', 'amount' => '350000.00'], ['code' => 'women_senior', 'label' => 'Woman or aged 65 and over', 'amount' => '400000.00'],
                        ['code' => 'disabled', 'label' => 'Person with disability', 'amount' => '475000.00'], ['code' => 'freedom_fighter', 'label' => 'Gazetted war-wounded freedom fighter', 'amount' => '500000.00'],
                    ],
                    'minimum_tax' => [['code' => 'metro', 'label' => 'Dhaka and Chattogram city corporations', 'amount' => '5000.00'], ['code' => 'city', 'label' => 'Other city corporations', 'amount' => '4000.00'], ['code' => 'other', 'label' => 'Elsewhere', 'amount' => '3000.00']],
                    'exemption_fraction' => '1/3', 'exemption_cap' => '450000.00', 'is_active' => true,
                ]);

                foreach ([['100000', '5'], ['300000', '10'], ['400000', '15'], ['500000', '20'], [null, '25']] as $order => [$width, $rate]) {
                    TaxSlab::query()->create(['tax_table_id' => $table->id, 'sort_order' => $order + 1, 'width' => $width, 'rate' => $rate]);
                }

                $made['tax_tables']++;
            }

            return $made;
        });
    }
}
