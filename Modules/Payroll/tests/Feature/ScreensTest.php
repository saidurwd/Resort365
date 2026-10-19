<?php

/*
| The Payroll screens (Step 6.4): components, structures with a try-out, income tax tables, salaries, and who may use them.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\TaxTable;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/payroll-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    pyPrepare();
});

it('manages salary components through the screens', function (): void {
    staffUser(DefaultRole::PayrollOfficer);

    get(tenantUrl('sunrise', '/payroll/components'))->assertOk()->assertSeeHtml('data-component="BASIC"')->assertSeeHtml('data-component="FBONUS"');
    get(tenantUrl('sunrise', '/payroll/components/create'))->assertOk()->assertSeeHtml('data-component-form');
    post(tenantUrl('sunrise', '/payroll/components'), ['code' => '', 'kind' => 'wrong'])->assertSessionHasErrors(['code', 'name', 'kind', 'frequency']);
    post(tenantUrl('sunrise', '/payroll/components'), ['code' => 'transport', 'name' => 'Transport allowance', 'kind' => 'earning', 'is_taxable' => '1', 'frequency' => 'monthly'])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/payroll/components'), ['code' => 'TRANSPORT', 'name' => 'Again', 'kind' => 'earning', 'frequency' => 'monthly'])->assertSessionHas('error');
    $transport = pyComponent('TRANSPORT');
    get(tenantUrl('sunrise', "/payroll/components/{$transport->id}/edit"))->assertOk()->assertSee('Transport allowance');
    put(tenantUrl('sunrise', "/payroll/components/{$transport->id}"), ['code' => 'TRANSPORT', 'name' => 'Transport', 'kind' => 'earning', 'frequency' => 'monthly'])->assertSessionHas('success');
    delete(tenantUrl('sunrise', '/payroll/components/'.pyComponent('TAX')->id))->assertSessionHas('error');
    delete(tenantUrl('sunrise', "/payroll/components/{$transport->id}"))->assertSessionHas('success');
    expect(booking(fn (): bool => SalaryComponent::query()->where('code', 'TRANSPORT')->exists()))->toBeFalse();
});

it('shows a structure and works out a month for a sample basic salary', function (): void {
    staffUser(DefaultRole::PayrollOfficer);
    $structure = pyStructure();

    get(tenantUrl('sunrise', '/payroll/structures'))->assertOk()->assertSeeHtml('data-structure="STD"');
    get(tenantUrl('sunrise', "/payroll/structures/{$structure->id}?basic=25000&category=general&area=metro"))->assertOk()->assertSeeHtml('data-structure-lines')->assertSeeHtml('data-breakdown')
        ->assertSeeHtml('data-line="HRENT"')->assertSee('50% of BASIC')->assertSee('BASIC / 12')->assertSee('42,500.00')->assertSee('39,583.33')->assertSee('416.67')->assertSeeHtml('data-tax-working');
    get(tenantUrl('sunrise', "/payroll/structures/{$structure->id}?basic=100000&category=general&area=metro"))->assertOk()->assertSee('162,500.00')->assertSee('17,291.67')->assertSee('135,208.33');
    get(tenantUrl('sunrise', "/payroll/structures/{$structure->id}?basic=abc"))->assertSessionHasErrors('basic');
});

it('adds, changes and deletes a structure, and refuses one that cannot be worked out', function (): void {
    staffUser(DefaultRole::PayrollOfficer);
    $line = fn (string $code, array $extra = []) => ['salary_component_id' => pyComponent($code)->id, 'calc_type' => 'fixed', ...$extra];

    get(tenantUrl('sunrise', '/payroll/structures/create'))->assertOk()->assertSeeHtml('data-structure-form')->assertSeeHtml('data-add-line');
    post(tenantUrl('sunrise', '/payroll/structures'), ['code' => 'X', 'name' => 'X'])->assertSessionHasErrors('lines');
    post(tenantUrl('sunrise', '/payroll/structures'), ['code' => 'LOOP', 'name' => 'Loop', 'lines' => [$line('BASIC'), $line('HRENT', ['calc_type' => 'percent', 'percent' => '50', 'percent_of' => 'MEDICAL']), $line('MEDICAL', ['calc_type' => 'percent', 'percent' => '5', 'percent_of' => 'HRENT'])]])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/payroll/structures'), ['code' => 'MGMT', 'name' => 'Management', 'lines' => [$line('BASIC'), $line('HRENT', ['calc_type' => 'percent', 'percent' => '40', 'percent_of' => 'BASIC']), $line('MEDICAL', ['calc_type' => 'formula', 'formula' => 'MIN(BASIC * 0.1, 5000)'])]])->assertSessionHas('success');
    $management = pyStructure('MGMT');
    expect(booking(fn (): int => $management->lines()->count()))->toBe(3);

    get(tenantUrl('sunrise', "/payroll/structures/{$management->id}/edit"))->assertOk()->assertSee('MIN(BASIC * 0.1, 5000)');
    put(tenantUrl('sunrise', "/payroll/structures/{$management->id}"), ['code' => 'MGMT', 'name' => 'Management team', 'lines' => [$line('BASIC')]])->assertSessionHas('success');
    delete(tenantUrl('sunrise', '/payroll/structures/'.pyStructure()->id.''))->assertSessionHas('success');
    expect(booking(fn (): bool => SalaryStructure::query()->where('code', 'STD')->exists()))->toBeFalse()->and(booking(fn (): int => $management->lines()->count()))->toBe(1);
});

it('shows the income tax tables and manages them', function (): void {
    staffUser(DefaultRole::PayrollOfficer);

    get(tenantUrl('sunrise', '/payroll/tax-tables'))->assertOk()->assertSeeHtml('data-tax-table="2025-26"')->assertSee('350,000.00')->assertSee('Woman or aged 65 and over')->assertSee('1/3')->assertSee('The rest');
    get(tenantUrl('sunrise', '/payroll/tax-tables/create'))->assertOk()->assertSeeHtml('data-tax-table-form');
    post(tenantUrl('sunrise', '/payroll/tax-tables'), ['name' => ''])->assertSessionHasErrors(['name', 'tax_year', 'effective_from', 'thresholds', 'slabs']);
    $data = ['name' => 'Income tax 2026-27', 'tax_year' => '2026-27', 'effective_from' => '2026-07-01', 'exemption_fraction' => '1/3', 'exemption_cap' => '450000', 'is_active' => '1',
        'thresholds' => [['code' => 'general', 'label' => 'General', 'amount' => '375000']], 'minimum_tax' => [['code' => 'metro', 'label' => 'Metro', 'amount' => '5000']], 'slabs' => [['width' => '100000', 'rate' => '5'], ['width' => '', 'rate' => '25']]];
    post(tenantUrl('sunrise', '/payroll/tax-tables'), [...$data, 'slabs' => [['width' => '100000', 'rate' => '5']]])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/payroll/tax-tables'), $data)->assertSessionHas('success');

    $table = booking(fn (): TaxTable => TaxTable::query()->where('tax_year', '2026-27')->firstOrFail());
    get(tenantUrl('sunrise', "/payroll/tax-tables/{$table->id}/edit"))->assertOk()->assertSee('Income tax 2026-27');
    put(tenantUrl('sunrise', "/payroll/tax-tables/{$table->id}"), [...$data, 'name' => 'Income tax FY27'])->assertSessionHas('success');
    delete(tenantUrl('sunrise', "/payroll/tax-tables/{$table->id}"))->assertSessionHas('success');
    expect(booking(fn (): int => TaxTable::query()->count()))->toBe(1);
});

it('lists the property\'s employees with their pay, and sets, shows and removes a salary', function (): void {
    $employee = hrEmployee();
    $other = hrEmployee(['first_name' => 'Nusrat']);
    pySalary($employee, '25000');
    staffUser(DefaultRole::PayrollOfficer);

    get(tenantUrl('sunrise', '/payroll/salaries'))->assertOk()->assertSeeHtml('data-salary-row="EMP-0001"')->assertSee('42,500.00')->assertSee('39,583.33')->assertSeeHtml('data-salary-row="EMP-0002"')->assertSee('No salary set');
    get(tenantUrl('sunrise', "/payroll/salaries/{$employee->id}"))->assertOk()->assertSeeHtml('data-salary-history')->assertSeeHtml('data-breakdown')->assertSee('416.67')->assertSeeHtml('data-salary-form');
    get(tenantUrl('sunrise', "/payroll/salaries/{$other->id}"))->assertOk()->assertSee('No salary on this date');
    post(tenantUrl('sunrise', "/payroll/salaries/{$other->id}"), ['basic' => '0'])->assertSessionHasErrors(['salary_structure_id', 'effective_from', 'basic', 'tax_category', 'tax_area']);
    post(tenantUrl('sunrise', "/payroll/salaries/{$other->id}"), ['salary_structure_id' => pyStructure()->id, 'effective_from' => '2025-09-01', 'basic' => '100000', 'tax_category' => 'general', 'tax_area' => 'metro', 'amounts' => ['CONV' => '3000']])->assertSessionHas('success');
    get(tenantUrl('sunrise', "/payroll/salaries/{$other->id}"))->assertOk()->assertSee('163,000.00')->assertSee('17,416.67');
    get(tenantUrl('sunrise', '/payroll/salaries/999999'))->assertNotFound();

    $future = pySalary($other, '120000', now()->addMonths(3)->startOfMonth()->toDateString());
    post(tenantUrl('sunrise', "/payroll/salaries/{$other->id}"), ['salary_structure_id' => pyStructure()->id, 'effective_from' => '2025-09-01', 'basic' => '1', 'tax_category' => 'general', 'tax_area' => 'metro'])->assertSessionHas('error');
    delete(tenantUrl('sunrise', "/payroll/salaries/{$other->id}/{$future->id}"))->assertSessionHas('success');
    delete(tenantUrl('sunrise', "/payroll/salaries/{$employee->id}/".booking(fn (): int => EmployeeSalary::query()->where('employee_id', $employee->id)->value('id'))))->assertSessionHas('error');
});

it('keeps payroll to the people who hold the permissions', function (): void {
    $employee = hrEmployee();
    pySalary($employee);
    $structure = pyStructure();

    staffUser(DefaultRole::FrontDeskAgent);
    foreach (['/payroll/salaries', "/payroll/salaries/{$employee->id}", '/payroll/structures', "/payroll/structures/{$structure->id}", '/payroll/components', '/payroll/tax-tables'] as $path) {
        get(tenantUrl('sunrise', $path))->assertForbidden();
    }

    staffUser(DefaultRole::Accountant);
    get(tenantUrl('sunrise', '/payroll/components'))->assertOk()->assertDontSee('New component');
    get(tenantUrl('sunrise', '/payroll/structures'))->assertOk();
    get(tenantUrl('sunrise', '/payroll/tax-tables'))->assertOk()->assertDontSee('New tax table');
    get(tenantUrl('sunrise', '/payroll/salaries'))->assertForbidden();
    post(tenantUrl('sunrise', '/payroll/components'), ['code' => 'Z', 'name' => 'Z', 'kind' => 'earning', 'frequency' => 'monthly'])->assertForbidden();

    staffUser(DefaultRole::HrManager);
    get(tenantUrl('sunrise', '/payroll/salaries'))->assertOk()->assertDontSee('Set it');
    get(tenantUrl('sunrise', "/payroll/salaries/{$employee->id}"))->assertOk()->assertDontSeeHtml('data-salary-form');
    post(tenantUrl('sunrise', "/payroll/salaries/{$employee->id}"), ['salary_structure_id' => $structure->id, 'effective_from' => '2026-09-01', 'basic' => '50000', 'tax_category' => 'general', 'tax_area' => 'metro'])->assertForbidden();
    get(tenantUrl('sunrise', '/payroll/tax-tables'))->assertForbidden();

    staffUser(DefaultRole::GeneralManager);
    get(tenantUrl('sunrise', '/payroll/salaries'))->assertOk();
    post(tenantUrl('sunrise', "/payroll/salaries/{$employee->id}"), ['salary_structure_id' => $structure->id, 'effective_from' => '2026-09-01', 'basic' => '50000', 'tax_category' => 'general', 'tax_area' => 'metro'])->assertSessionHas('success');
    expect(booking(fn (): int => EmployeeSalary::query()->count()))->toBe(2);
});
