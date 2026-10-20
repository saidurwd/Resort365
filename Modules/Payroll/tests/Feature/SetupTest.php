<?php

/*
| Salary setup (Step 6.4): the Bangladesh starter pack, components, structures and income tax tables.
*/

use App\Models\Tenant;
use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payroll\Actions\DeleteSalaryComponent;
use Modules\Payroll\Actions\DeleteSalaryStructure;
use Modules\Payroll\Actions\SaveSalaryComponent;
use Modules\Payroll\Actions\SaveSalaryStructure;
use Modules\Payroll\Actions\SaveTaxTable;
use Modules\Payroll\Actions\SeedBangladeshPayroll;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Listeners\SeedPayrollForNewTenant;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\TaxTable;

require_once __DIR__.'/../Support/payroll-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    pyPrepare();
});

function pyComponent(string $code): SalaryComponent
{
    return booking(fn (): SalaryComponent => SalaryComponent::query()->where('code', $code)->firstOrFail());
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pyLine(string $code, string $type, array $overrides = []): array
{
    return ['salary_component_id' => pyComponent($code)->id, 'calc_type' => $type, ...$overrides];
}

it('gives a tenant the Bangladesh starter pack, once', function (): void {
    $components = booking(fn (): array => SalaryComponent::query()->orderBy('sort_order')->pluck('name', 'code')->all());
    $structure = booking(fn (): SalaryStructure => SalaryStructure::query()->with('lines.component')->where('code', 'STD')->firstOrFail());
    $table = booking(fn (): TaxTable => TaxTable::query()->with('slabs')->sole());

    expect(array_keys($components))->toBe(['BASIC', 'HRENT', 'MEDICAL', 'CONV', 'FBONUS', 'OT', 'SCSHARE', 'TIPSHARE', 'PF_EMP', 'TAX', 'LOAN', 'ADVANCE', 'ABSENCE', 'PF_ER', 'GRAT'])
        ->and(pyComponent('FBONUS')->frequency)->toBe(ComponentFrequency::Annual)->and(pyComponent('FBONUS')->annual_times)->toBe(2)->and(pyComponent('TAX')->is_system)->toBeTrue()
        ->and($structure->lines->pluck('component.code')->all())->toBe(['BASIC', 'HRENT', 'MEDICAL', 'CONV', 'FBONUS', 'PF_EMP', 'TAX', 'PF_ER', 'GRAT'])
        ->and($table->tax_year)->toBe('2025-26')->and($table->slabs->pluck('rate')->map(fn ($rate): string => (string) $rate)->all())->toBe(['5.00', '10.00', '15.00', '20.00', '25.00'])->and($table->slabs->last()->width)->toBeNull()
        ->and(collect($table->thresholds)->pluck('amount', 'code')->all())->toBe(['general' => '350000.00', 'women_senior' => '400000.00', 'disabled' => '475000.00', 'freedom_fighter' => '500000.00'])
        ->and(booking(fn (): array => app(SeedBangladeshPayroll::class)->handle()))->toBe(['components' => 0, 'structures' => 0, 'tax_tables' => 0]);

    $other = Tenant::factory()->create(['slug' => 'other']);
    app(SeedPayrollForNewTenant::class)->handle(new TenantCreated($other));
    expect(app(TenantContext::class)->run($other, fn (): int => SalaryComponent::query()->count()))->toBe(15);
});

it('saves a component, refusing a bad or duplicate code and a change to a system component\'s code', function (): void {
    $save = fn (?SalaryComponent $component, array $data) => booking(fn (): SalaryComponent => app(SaveSalaryComponent::class)->handle($component, $data));
    $transport = $save(null, ['code' => 'transport', 'name' => 'Transport allowance', 'kind' => 'earning', 'is_taxable' => true, 'frequency' => 'monthly', 'sort_order' => 45]);

    expect($transport->code)->toBe('TRANSPORT')->and($transport->is_taxable)->toBeTrue()->and($transport->is_system)->toBeFalse()
        ->and(fn () => $save(null, ['code' => '1BAD', 'name' => 'x', 'kind' => 'earning']))->toThrow(PayrollRuleViolated::class, 'starts with a letter')
        ->and(fn () => $save(null, ['code' => 'basic', 'name' => 'Again', 'kind' => 'earning']))->toThrow(PayrollRuleViolated::class, 'already a component')
        ->and(fn () => $save(pyComponent('TAX'), ['code' => 'TAX2', 'name' => 'Tax', 'kind' => 'deduction']))->toThrow(PayrollRuleViolated::class, 'cannot be changed')
        ->and(fn () => $save(null, ['code' => 'LEVY', 'name' => 'Levy', 'kind' => 'deduction', 'frequency' => 'annual']))->toThrow(PayrollRuleViolated::class, 'Only earnings')
        ->and($save(pyComponent('BASIC'), ['code' => 'BASIC', 'name' => 'Basic pay', 'kind' => 'earning', 'is_taxable' => true])->name)->toBe('Basic pay')
        ->and($save(null, ['code' => 'DED', 'name' => 'Deduction', 'kind' => 'deduction', 'is_taxable' => true])->is_taxable)->toBeFalse();
});

it('deletes a component no structure uses, and not a system one or one in use', function (): void {
    $extra = booking(fn (): SalaryComponent => app(SaveSalaryComponent::class)->handle(null, ['code' => 'XTRA', 'name' => 'Extra', 'kind' => 'earning']));
    booking(fn () => app(DeleteSalaryComponent::class)->handle($extra));

    expect(booking(fn (): bool => SalaryComponent::query()->where('code', 'XTRA')->exists()))->toBeFalse()
        ->and(fn () => booking(fn () => app(DeleteSalaryComponent::class)->handle(pyComponent('TAX'))))->toThrow(PayrollRuleViolated::class, 'system component')
        ->and(fn () => booking(fn () => app(DeleteSalaryComponent::class)->handle(pyComponent('HRENT'))))->toThrow(PayrollRuleViolated::class, 'used in a salary structure');
});

it('saves a structure and tries it out: percentages, formulas and amounts set per employee', function (): void {
    $structure = booking(fn (): SalaryStructure => app(SaveSalaryStructure::class)->handle(null, ['code' => 'mgmt', 'name' => 'Management', 'lines' => [
        pyLine('BASIC', 'fixed'), pyLine('HRENT', 'percent', ['percent' => '40', 'percent_of' => 'basic']), pyLine('MEDICAL', 'formula', ['formula' => 'MIN(BASIC * 0.1, 5000)']), pyLine('CONV', 'employee', ['amount' => '3000']),
        pyLine('PF_EMP', 'percent', ['percent' => '10', 'percent_of' => 'BASIC']),
    ]]));

    expect($structure->code)->toBe('MGMT')->and($structure->lines)->toHaveCount(5)->and($structure->lines[1]->percent_of)->toBe('BASIC')->and($structure->lines[2]->formula)->toBe('MIN(BASIC * 0.1, 5000)');

    $changed = booking(fn (): SalaryStructure => app(SaveSalaryStructure::class)->handle($structure, ['code' => 'MGMT', 'name' => 'Management team', 'lines' => [pyLine('BASIC', 'fixed'), pyLine('CONV', 'employee', ['amount' => '3500'])]]));
    expect($changed->lines)->toHaveCount(2)->and($changed->name)->toBe('Management team');
});

it('refuses a structure with a circle, an unknown or missing component, a bad formula, or the same component twice', function (): void {
    $save = fn (array $lines) => booking(fn () => app(SaveSalaryStructure::class)->handle(null, ['code' => 'T'.random_int(100, 999), 'name' => 'Try', 'lines' => $lines]));

    expect(fn () => $save([pyLine('BASIC', 'fixed'), pyLine('HRENT', 'percent', ['percent' => '50', 'percent_of' => 'MEDICAL']), pyLine('MEDICAL', 'percent', ['percent' => '10', 'percent_of' => 'HRENT'])]))->toThrow(PayrollRuleViolated::class, 'circle')
        ->and(fn () => $save([pyLine('BASIC', 'fixed'), pyLine('HRENT', 'percent', ['percent' => '50', 'percent_of' => 'NOPE'])]))->toThrow(PayrollRuleViolated::class, 'NOPE')
        ->and(fn () => $save([pyLine('BASIC', 'fixed'), pyLine('HRENT', 'formula', ['formula' => 'BASIC *'])]))->toThrow(PayrollRuleViolated::class, 'ends too soon')
        ->and(fn () => $save([pyLine('BASIC', 'fixed'), pyLine('HRENT', 'formula', ['formula' => 'BASIC / 0'])]))->toThrow(PayrollRuleViolated::class, 'divides by zero')
        ->and(fn () => $save([pyLine('BASIC', 'fixed'), pyLine('HRENT', 'formula', ['formula' => 'BASIC; DROP'])]))->toThrow(PayrollRuleViolated::class, 'does not understand')
        ->and(fn () => $save([pyLine('BASIC', 'fixed'), pyLine('BASIC', 'fixed')]))->toThrow(PayrollRuleViolated::class, 'twice')
        ->and(fn () => $save([pyLine('HRENT', 'percent', ['percent' => '', 'percent_of' => 'BASIC'])]))->toThrow(PayrollRuleViolated::class, 'Enter a number')
        ->and(fn () => $save([pyLine('HRENT', 'percent', ['percent' => '10', 'percent_of' => ''])]))->toThrow(PayrollRuleViolated::class, 'percentage of')
        ->and(fn () => $save([]))->toThrow(PayrollRuleViolated::class, 'at least one')
        ->and(fn () => booking(fn () => app(SaveSalaryStructure::class)->handle(null, ['code' => 'std', 'name' => 'Dup', 'lines' => [pyLine('BASIC', 'fixed')]])))->toThrow(PayrollRuleViolated::class, 'already a structure');
});

it('deletes a structure nobody is paid by, and not one that is assigned', function (): void {
    $spare = booking(fn (): SalaryStructure => app(SaveSalaryStructure::class)->handle(null, ['code' => 'SPARE', 'name' => 'Spare', 'lines' => [pyLine('BASIC', 'fixed')]]));
    booking(fn () => app(DeleteSalaryStructure::class)->handle($spare));
    pySalary(hrEmployee());

    expect(booking(fn (): bool => SalaryStructure::query()->where('code', 'SPARE')->exists()))->toBeFalse()
        ->and(fn () => booking(fn () => app(DeleteSalaryStructure::class)->handle(pyStructure())))->toThrow(PayrollRuleViolated::class, 'assigned to employees');
});

it('saves a tax table with its categories, areas and slabs, and refuses a table that does not work', function (): void {
    $data = ['name' => 'Income tax 2026-27', 'tax_year' => '2026-27', 'effective_from' => '2026-07-01', 'exemption_fraction' => '0.25', 'exemption_cap' => '400000',
        'thresholds' => [['code' => 'general', 'label' => 'General', 'amount' => '375000'], ['code' => 'woman', 'label' => 'Woman', 'amount' => '425000']], 'minimum_tax' => [['code' => 'metro', 'label' => 'Metro', 'amount' => '6000']],
        'slabs' => [['width' => '100000', 'rate' => '5'], ['width' => '', 'rate' => '30']]];
    $table = booking(fn (): TaxTable => app(SaveTaxTable::class)->handle(null, $data));
    $save = fn (array $override) => booking(fn () => app(SaveTaxTable::class)->handle(null, [...$data, ...$override]));

    expect($table->exemption_fraction)->toBe('0.25')->and($table->thresholds[1]['amount'])->toBe('425000.00')->and($table->slabs)->toHaveCount(2)
        ->and(fn () => $save(['slabs' => [['width' => '100000', 'rate' => '5']]]))->toThrow(PayrollRuleViolated::class, 'no width')
        ->and(fn () => $save(['slabs' => [['width' => '', 'rate' => '5'], ['width' => '', 'rate' => '30']]]))->toThrow(PayrollRuleViolated::class, 'Only the last')
        ->and(fn () => $save(['slabs' => [['width' => '100000', 'rate' => '150'], ['width' => '', 'rate' => '30']]]))->toThrow(PayrollRuleViolated::class, 'between 0 and 100')
        ->and(fn () => $save(['thresholds' => []]))->toThrow(PayrollRuleViolated::class, 'at least one')
        ->and(fn () => $save(['thresholds' => [['code' => 'a', 'amount' => '1'], ['code' => 'a', 'amount' => '2']]]))->toThrow(PayrollRuleViolated::class, 'its own code')
        ->and(fn () => $save(['exemption_fraction' => 'a third']))->toThrow(PayrollRuleViolated::class, 'decimal');
});
