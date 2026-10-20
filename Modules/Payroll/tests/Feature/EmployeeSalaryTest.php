<?php

/*
| Employee salaries (Step 6.4): effective-dated records, the month's breakdown from the structure and the tax table in force.
| "Done when": an employee's monthly gross, deductions and tax are shown correctly for the configured structure.
*/

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payroll\Actions\DeleteEmployeeSalary;
use Modules\Payroll\Actions\SaveSalaryStructure;
use Modules\Payroll\Actions\SaveTaxTable;
use Modules\Payroll\Actions\SetEmployeeSalary;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Services\SalaryPreview;

require_once __DIR__.'/../Support/payroll-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    pyPrepare();
});

function esRecord(EmployeeSalary $salary): EmployeeSalary
{
    return booking(fn (): EmployeeSalary => EmployeeSalary::query()->findOrFail($salary->id));
}

it('shows an employee\'s month from the structure: gross, deductions, tax and net (done when)', function (): void {
    $employee = hrEmployee();
    pySalary($employee, '25000');
    $pay = booking(fn () => app(SalaryPreview::class)->forEmployee($employee->id, '2026-03-01'));

    expect($pay?->gross)->toBe('42500.00')->and($pay?->deductions)->toBe('2500.00')->and($pay?->tax)->toBe('416.67')->and($pay?->net)->toBe('39583.33')->and($pay?->taxDetails?->tax)->toBe('5000.00')
        ->and(booking(fn () => app(SalaryPreview::class)->forEmployee($employee->id, '2025-07-31')))->toBeNull();
});

it('uses the amount set for the employee, the taxpayer category and the tax area', function (): void {
    $employee = hrEmployee();
    $salary = pySalary($employee, '100000', '2025-08-01', ['tax_category' => 'women_senior', 'tax_area' => 'city', 'amounts' => ['CONV' => '3000', 'XXX' => '']]);
    $pay = booking(fn () => app(SalaryPreview::class)->forEmployee($employee->id, '2026-03-01'));

    // Gross 100,000 + 50,000 + 10,000 + 3,000. With the 400,000 tax-free amount the year's tax is 196,500, far over the city minimum.
    expect($pay?->gross)->toBe('163000.00')->and(esRecord($salary)->amounts)->toBe(['CONV' => '3000.00'])->and($pay?->taxDetails?->minimumApplied)->toBeFalse()
        ->and($pay?->taxDetails?->threshold)->toBe('400000.00');
});

it('closes the earlier salary the day before a new one starts, and the new one before the next', function (): void {
    $employee = hrEmployee();
    $now = CarbonImmutable::now();
    $first = pySalary($employee, '25000', '2025-08-01');
    $third = pySalary($employee, '40000', $now->addMonths(3)->startOfMonth()->toDateString());
    $second = pySalary($employee, '30000', $now->addMonth()->startOfMonth()->toDateString());
    $preview = fn (string $date) => booking(fn () => app(SalaryPreview::class)->forEmployee($employee->id, $date));

    expect(esRecord($first)->effective_to?->toDateString())->toBe($now->addMonth()->startOfMonth()->subDay()->toDateString())->and(esRecord($second)->effective_to?->toDateString())->toBe($now->addMonths(3)->startOfMonth()->subDay()->toDateString())
        ->and(esRecord($third)->effective_to)->toBeNull()->and(booking(fn () => app(SalaryPreview::class)->salaryOn($employee->id, $now->addMonths(2)->toDateString()))?->id)->toBe($second->id)
        ->and($preview($now->addMonths(4)->toDateString())?->gross)->toBe('66500.00')->and($preview($now->toDateString())?->gross)->toBe('42500.00');
});

it('refuses a salary on a date that has one, before one that has started, with a bad basic, structure, category or area', function (): void {
    $employee = hrEmployee();
    pySalary($employee, '25000', '2025-08-01');
    pySalary($employee, '30000', '2026-01-01');
    $inactive = booking(fn (): SalaryStructure => SalaryStructure::factory()->create(['is_active' => false]));
    $set = fn (array $data) => booking(fn () => app(SetEmployeeSalary::class)->handle($employee->id, [
        'salary_structure_id' => pyStructure()->id, 'effective_from' => '2026-06-01', 'basic' => '35000', 'tax_category' => 'general', 'tax_area' => 'metro', ...$data,
    ], 1));

    expect(fn () => $set(['effective_from' => '2026-01-01']))->toThrow(PayrollRuleViolated::class, 'already has a salary from')
        ->and(fn () => $set(['effective_from' => '2025-10-01']))->toThrow(PayrollRuleViolated::class, 'later salary has already started')
        ->and(fn () => $set(['basic' => '0']))->toThrow(PayrollRuleViolated::class, 'basic salary')
        ->and(fn () => $set(['salary_structure_id' => $inactive->id]))->toThrow(PayrollRuleViolated::class, 'active salary structure')
        ->and(fn () => $set(['tax_category' => 'martian']))->toThrow(PayrollRuleViolated::class, 'taxpayer category')
        ->and(fn () => $set(['tax_area' => 'moon']))->toThrow(PayrollRuleViolated::class, 'tax area')
        ->and(fn () => $set(['amounts' => ['CONV' => '-5']]))->toThrow(PayrollRuleViolated::class, 'Enter a number')
        ->and(fn () => booking(fn () => app(SetEmployeeSalary::class)->handle(999999, ['salary_structure_id' => pyStructure()->id, 'effective_from' => '2026-06-01', 'basic' => '1000'], 1)))->toThrow(PayrollRuleViolated::class, 'does not exist');
});

it('removes a salary that has not started, extending the one before it, and keeps one that has', function (): void {
    $employee = hrEmployee();
    $started = pySalary($employee, '25000', '2025-08-01');
    $future = pySalary($employee, '40000', now()->addMonths(2)->startOfMonth()->toDateString());
    $delete = fn (EmployeeSalary $salary) => booking(fn () => app(DeleteEmployeeSalary::class)->handle(esRecord($salary)));

    expect(esRecord($started)->effective_to)->not->toBeNull();
    $delete($future);
    expect(esRecord($started)->effective_to)->toBeNull()->and(booking(fn (): bool => EmployeeSalary::query()->whereKey($future->id)->exists()))->toBeFalse()
        ->and(fn () => $delete($started))->toThrow(PayrollRuleViolated::class, 'has started');
});

it('works out a different structure with its own components, and follows the tax table in force on the date', function (): void {
    $employee = hrEmployee();
    $casual = booking(fn (): SalaryStructure => SalaryStructure::query()->where('code', 'CASUAL')->firstOrFail());
    pySalary($employee, '30000', '2025-08-01', ['salary_structure_id' => $casual->id]);
    $preview = fn (string $date) => booking(fn () => app(SalaryPreview::class)->forEmployee($employee->id, $date));

    // BASIC only: 360,000 a year, a third exempt (120,000), 240,000 is under the 350,000 tax-free amount.
    expect($preview('2026-03-01')?->gross)->toBe('30000.00')->and($preview('2026-03-01')?->tax)->toBe('0.00');

    // A later table with a lower tax-free amount takes over from its date.
    booking(fn () => app(SaveTaxTable::class)->handle(null, ['name' => 'Income tax 2026-27', 'tax_year' => '2026-27', 'effective_from' => '2026-07-01', 'exemption_fraction' => '0', 'exemption_cap' => '0',
        'thresholds' => [['code' => 'general', 'label' => 'General', 'amount' => '120000']], 'minimum_tax' => [['code' => 'metro', 'label' => 'Metro', 'amount' => '1000']], 'slabs' => [['width' => '', 'rate' => '10']]]));
    expect($preview('2026-08-01')?->taxDetails?->tax)->toBe('24000.00')->and($preview('2026-08-01')?->tax)->toBe('2000.00')->and($preview('2026-03-01')?->tax)->toBe('0.00');
});

it('stays correct when a structure line is changed: the new structure is used from then on', function (): void {
    $employee = hrEmployee();
    pySalary($employee, '25000');
    $structure = pyStructure();
    $lines = booking(fn (): array => $structure->lines()->with('component')->get()->map(fn ($line): array => [
        'salary_component_id' => $line->salary_component_id, 'calc_type' => $line->calc_type->value, 'amount' => $line->amount, 'percent' => $line->component->code === 'HRENT' ? '60' : $line->percent,
        'percent_of' => $line->percent_of, 'formula' => $line->formula,
    ])->all());
    booking(fn () => app(SaveSalaryStructure::class)->handle($structure, ['code' => 'STD', 'name' => $structure->name, 'lines' => $lines]));

    expect(booking(fn () => app(SalaryPreview::class)->forEmployee($employee->id, '2026-03-01'))?->gross)->toBe('45000.00')->and(booking(fn (): int => SalaryComponent::query()->count()))->toBe(15);
});
