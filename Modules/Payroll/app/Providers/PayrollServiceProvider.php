<?php

namespace Modules\Payroll\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Support\Facades\Gate;
use Modules\Payroll\Console\SeedBangladeshCommand;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\TaxTable;
use Modules\Payroll\Policies\EmployeeSalaryPolicy;
use Modules\Payroll\Policies\SalaryComponentPolicy;
use Modules\Payroll\Policies\SalaryStructurePolicy;
use Modules\Payroll\Policies\TaxTablePolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class PayrollServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Payroll';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'payroll';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SeedBangladeshCommand::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(SalaryComponent::class, SalaryComponentPolicy::class);
        Gate::policy(SalaryStructure::class, SalaryStructurePolicy::class);
        Gate::policy(TaxTable::class, TaxTablePolicy::class);
        Gate::policy(EmployeeSalary::class, EmployeeSalaryPolicy::class);

        $this->registerPermissions($this->app->make(PermissionRegistry::class));
        $this->registerMenu($this->app->make(MenuRegistry::class));
    }

    private function registerPermissions(PermissionRegistry $permissions): void
    {
        $payroll = [DefaultRole::GeneralManager, DefaultRole::PayrollOfficer];

        $permissions->register('Payroll', [
            new PermissionDefinition('payroll.component.view', 'View salary components', [...$payroll, DefaultRole::HrManager, DefaultRole::Accountant]),
            new PermissionDefinition('payroll.component.manage', 'Change salary components', $payroll),
            new PermissionDefinition('payroll.structure.view', 'View salary structures', [...$payroll, DefaultRole::HrManager, DefaultRole::Accountant]),
            new PermissionDefinition('payroll.structure.manage', 'Change salary structures', $payroll),
            new PermissionDefinition('payroll.salary.view', 'View employees\' salaries', [...$payroll, DefaultRole::HrManager]),
            new PermissionDefinition('payroll.salary.manage', 'Set employees\' salaries', $payroll),
            new PermissionDefinition('payroll.tax.view', 'View income tax tables', [...$payroll, DefaultRole::Accountant]),
            new PermissionDefinition('payroll.tax.manage', 'Change income tax tables', $payroll),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('payroll', 'Payroll', 'bi-cash-coin', order: 420);
        $menu->add(new MenuItem('payroll.salaries', 'Salaries', route: 'payroll.salaries.index', parent: 'payroll', order: 10,
            permission: 'payroll.salary.view', module: 'payroll', active: 'payroll.salaries.*'));
        $menu->add(new MenuItem('payroll.structures', 'Salary structures', route: 'payroll.structures.index', parent: 'payroll', order: 20,
            permission: 'payroll.structure.view', module: 'payroll', active: 'payroll.structures.*'));
        $menu->add(new MenuItem('payroll.components', 'Salary components', route: 'payroll.components.index', parent: 'payroll', order: 30,
            permission: 'payroll.component.view', module: 'payroll', active: 'payroll.components.*'));
        $menu->add(new MenuItem('payroll.tax-tables', 'Income tax tables', route: 'payroll.tax-tables.index', parent: 'payroll', order: 40,
            permission: 'payroll.tax.view', module: 'payroll', active: 'payroll.tax-tables.*'));
    }
}
