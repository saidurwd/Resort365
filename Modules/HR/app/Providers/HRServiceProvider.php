<?php

namespace Modules\HR\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\Enums\SequenceReset;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Grade;
use Modules\HR\Policies\DesignationPolicy;
use Modules\HR\Policies\EmployeePolicy;
use Modules\HR\Policies\GradePolicy;
use Modules\HR\Services\EmployeeDirectoryService;
use Modules\HR\Services\NationalIdHasher;
use Nwidart\Modules\Support\ModuleServiceProvider;

class HRServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'HR';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'hr';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(EmployeeDirectory::class, EmployeeDirectoryService::class);
        $this->app->singleton(NationalIdHasher::class, fn (): NationalIdHasher => new NationalIdHasher('employee-id|'.config('app.key')));
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['employee' => Employee::class]);

        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Designation::class, DesignationPolicy::class);
        Gate::policy(Grade::class, GradePolicy::class);

        $this->app->make(DocumentNumbers::class)->register(new DocumentType('employee', 'Employee number', 'EMP', '{PREFIX}-{SEQ:4}', SequenceReset::Never));
        $this->registerPermissions($this->app->make(PermissionRegistry::class));
        $this->registerMenu($this->app->make(MenuRegistry::class));
    }

    private function registerPermissions(PermissionRegistry $permissions): void
    {
        $managers = [DefaultRole::GeneralManager, DefaultRole::HrManager];

        $permissions->register('HR', [
            new PermissionDefinition('hr.employee.view', 'View employees, designations and grades', [...$managers, DefaultRole::PayrollOfficer]),
            new PermissionDefinition('hr.employee.manage', 'Add and change employees', $managers),
            new PermissionDefinition('hr.employee.view-sensitive', 'See employees\' national ID, passport and bank account numbers', [DefaultRole::HrManager, DefaultRole::PayrollOfficer]),
            new PermissionDefinition('hr.lifecycle.manage', 'Confirm, transfer, promote, give increments to, discipline and exit employees', $managers),
            new PermissionDefinition('hr.setup.manage', 'Change designations and grades', $managers),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('hr', 'HR', 'bi-people', order: 400);
        $menu->add(new MenuItem('hr.employees', 'Employees', route: 'hr.employees.index', parent: 'hr', order: 10,
            permission: 'hr.employee.view', module: 'hr', active: 'hr.employees.*'));

        $menu->group('setup', 'Setup', 'bi-gear', order: 900);
        $menu->add(new MenuItem('hr.designations', 'Designations', route: 'hr.designations.index', parent: 'setup', order: 40,
            permission: 'hr.employee.view', module: 'hr', active: 'hr.designations.*'));
        $menu->add(new MenuItem('hr.grades', 'Grades', route: 'hr.grades.index', parent: 'setup', order: 41,
            permission: 'hr.employee.view', module: 'hr', active: 'hr.grades.*'));
    }
}
