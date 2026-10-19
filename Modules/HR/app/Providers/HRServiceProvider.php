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
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Grade;
use Modules\HR\Models\Holiday;
use Modules\HR\Models\Roster;
use Modules\HR\Models\Shift;
use Modules\HR\Policies\AttendancePolicy;
use Modules\HR\Policies\DesignationPolicy;
use Modules\HR\Policies\EmployeePolicy;
use Modules\HR\Policies\GradePolicy;
use Modules\HR\Policies\HolidayPolicy;
use Modules\HR\Policies\RosterPolicy;
use Modules\HR\Policies\ShiftPolicy;
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
        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(Holiday::class, HolidayPolicy::class);
        Gate::policy(Roster::class, RosterPolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);

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
            new PermissionDefinition('hr.shift.view', 'View shifts', [...$managers, DefaultRole::PayrollOfficer]),
            new PermissionDefinition('hr.shift.manage', 'Change shifts', $managers),
            new PermissionDefinition('hr.roster.view', 'View the roster', [...$managers, DefaultRole::PayrollOfficer]),
            new PermissionDefinition('hr.roster.manage', 'Assign, clear and copy roster entries', $managers),
            new PermissionDefinition('hr.attendance.view', 'View attendance and the attendance report', [...$managers, DefaultRole::PayrollOfficer]),
            new PermissionDefinition('hr.attendance.record', 'Enter and correct attendance by hand', $managers),
            new PermissionDefinition('hr.attendance.import', 'Import attendance from biometric CSV files', [...$managers, DefaultRole::PayrollOfficer]),
            new PermissionDefinition('hr.holiday.view', 'View holidays', [...$managers, DefaultRole::PayrollOfficer]),
            new PermissionDefinition('hr.holiday.manage', 'Change holidays', $managers),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('hr', 'HR', 'bi-people', order: 400);
        $menu->add(new MenuItem('hr.employees', 'Employees', route: 'hr.employees.index', parent: 'hr', order: 10,
            permission: 'hr.employee.view', module: 'hr', active: 'hr.employees.*'));
        $menu->add(new MenuItem('hr.roster', 'Roster', route: 'hr.roster.index', parent: 'hr', order: 20,
            permission: 'hr.roster.view', module: 'hr', active: 'hr.roster.*'));
        $menu->add(new MenuItem('hr.attendance', 'Attendance', route: 'hr.attendance.index', parent: 'hr', order: 30,
            permission: 'hr.attendance.view', module: 'hr', active: ['hr.attendance.index', 'hr.attendance.import', 'hr.attendance.store']));
        $menu->add(new MenuItem('hr.attendance-report', 'Attendance report', route: 'hr.attendance.report', parent: 'hr', order: 40,
            permission: 'hr.attendance.view', module: 'hr', active: 'hr.attendance.report'));
        $menu->add(new MenuItem('hr.holidays', 'Holidays', route: 'hr.holidays.index', parent: 'hr', order: 50,
            permission: 'hr.holiday.view', module: 'hr', active: 'hr.holidays.*'));

        $menu->group('setup', 'Setup', 'bi-gear', order: 900);
        $menu->add(new MenuItem('hr.designations', 'Designations', route: 'hr.designations.index', parent: 'setup', order: 40,
            permission: 'hr.employee.view', module: 'hr', active: 'hr.designations.*'));
        $menu->add(new MenuItem('hr.shifts', 'Shifts', route: 'hr.shifts.index', parent: 'setup', order: 42,
            permission: 'hr.shift.view', module: 'hr', active: 'hr.shifts.*'));
        $menu->add(new MenuItem('hr.grades', 'Grades', route: 'hr.grades.index', parent: 'setup', order: 41,
            permission: 'hr.employee.view', module: 'hr', active: 'hr.grades.*'));
    }
}
