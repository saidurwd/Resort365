<?php

namespace Modules\Payroll\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\ApprovalDocumentType;
use Modules\Core\DTOs\ApprovalStepDefinition;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\Payroll\Console\SeedBangladeshCommand;
use Modules\Payroll\Contracts\PayrollLedgerFacts;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\TaxTable;
use Modules\Payroll\Policies\EmployeeSalaryPolicy;
use Modules\Payroll\Policies\LoanPolicy;
use Modules\Payroll\Policies\PayrollRunPolicy;
use Modules\Payroll\Policies\PayslipPolicy;
use Modules\Payroll\Policies\SalaryComponentPolicy;
use Modules\Payroll\Policies\SalaryStructurePolicy;
use Modules\Payroll\Policies\TaxTablePolicy;
use Modules\Payroll\Services\PayrollApprovalHandler;
use Modules\Payroll\Services\PayrollLedgerFactsService;
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

    public function register(): void
    {
        parent::register();

        $this->app->singleton(PayrollLedgerFacts::class, PayrollLedgerFactsService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(SalaryComponent::class, SalaryComponentPolicy::class);
        Gate::policy(SalaryStructure::class, SalaryStructurePolicy::class);
        Gate::policy(TaxTable::class, TaxTablePolicy::class);
        Gate::policy(EmployeeSalary::class, EmployeeSalaryPolicy::class);
        Gate::policy(PayrollRun::class, PayrollRunPolicy::class);
        Gate::policy(Payslip::class, PayslipPolicy::class);
        Gate::policy(Loan::class, LoanPolicy::class);

        $numbers = $this->app->make(DocumentNumbers::class);
        $numbers->register(new DocumentType('payroll_run', 'Payroll run', 'PRUN'));
        $numbers->register(new DocumentType('loan', 'Loan or salary advance', 'LN'));
        $this->app->make(ApprovalRegistry::class)->register(new ApprovalDocumentType(
            'payroll_run', 'Payroll run', 'A month of payroll for a property, approved before it is paid. The amount is the net pay; a large run also needs the General Manager.',
            [new ApprovalStepDefinition('Accounts approver', 'payroll.run.approve', '0.00'), new ApprovalStepDefinition('General Manager', 'payroll.run.approve-large', '2000000.00')],
        ), PayrollApprovalHandler::class);

        $settings = $this->app->make(Settings::class);
        $settings->define(new SettingDefinition('payroll.overtime_multiplier', 'Overtime pay multiplier', SettingType::Decimal, '2', SettingScope::Property, 'Payroll', help: 'Overtime is paid at this many times the hourly basic rate (the Labour Act says twice).'));
        $settings->define(new SettingDefinition('payroll.overtime_hours_per_month', 'Working hours in a month', SettingType::Decimal, '208', SettingScope::Property, 'Payroll', help: 'The basic salary is divided by this to get the hourly rate.'));
        $settings->define(new SettingDefinition('payroll.salary_days_basis', 'Days a monthly salary is divided by', SettingType::Integer, 30, SettingScope::Property, 'Payroll', help: 'A day of absence or unpaid leave costs the regular gross divided by this.'));

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
            new PermissionDefinition('payroll.run.view', 'View payroll runs, payslips and the register', [...$payroll, DefaultRole::Accountant]),
            new PermissionDefinition('payroll.run.create', 'Open, calculate, adjust and send payroll runs for approval', $payroll),
            new PermissionDefinition('payroll.run.approve', 'Approve payroll runs', [DefaultRole::GeneralManager, DefaultRole::Accountant]),
            new PermissionDefinition('payroll.run.approve-large', 'Approve payroll runs of 2,000,000 net or more', [DefaultRole::GeneralManager]),
            new PermissionDefinition('payroll.run.pay', 'Record the payment of a payroll run', [DefaultRole::GeneralManager, DefaultRole::Accountant]),
            new PermissionDefinition('payroll.run.export', 'Download the payroll register and the bank transfer file', [...$payroll, DefaultRole::Accountant]),
            new PermissionDefinition('payroll.payslip.view', 'View and download payslips', [...$payroll, DefaultRole::Accountant]),
            new PermissionDefinition('payroll.payslip.email', 'Email payslips to employees', $payroll),
            new PermissionDefinition('payroll.loan.view', 'View loans and salary advances', [...$payroll, DefaultRole::HrManager, DefaultRole::Accountant]),
            new PermissionDefinition('payroll.loan.manage', 'Give loans and advances, move and write off installments', $payroll),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('payroll', 'Payroll', 'bi-cash-coin', order: 420);
        $menu->add(new MenuItem('payroll.runs', 'Payroll runs', route: 'payroll.runs.index', parent: 'payroll', order: 5,
            permission: 'payroll.run.view', module: 'payroll', active: ['payroll.runs.*', 'payroll.payslips.*']));
        $menu->add(new MenuItem('payroll.loans', 'Loans and advances', route: 'payroll.loans.index', parent: 'payroll', order: 15,
            permission: 'payroll.loan.view', module: 'payroll', active: 'payroll.loans.*'));
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
