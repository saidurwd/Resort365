<?php

namespace Modules\HR\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\DTOs\ApprovalDocumentType;
use Modules\Core\DTOs\ApprovalStepDefinition;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\Enums\SequenceReset;
use Modules\HR\Console\GrantLeaveCommand;
use Modules\HR\Console\SeedLeaveTypesCommand;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\HR\Contracts\PayrollFacts;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Grade;
use Modules\HR\Models\Holiday;
use Modules\HR\Models\LeaveBalance;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\LeaveType;
use Modules\HR\Models\Roster;
use Modules\HR\Models\Shift;
use Modules\HR\Policies\AttendancePolicy;
use Modules\HR\Policies\DesignationPolicy;
use Modules\HR\Policies\EmployeePolicy;
use Modules\HR\Policies\GradePolicy;
use Modules\HR\Policies\HolidayPolicy;
use Modules\HR\Policies\LeaveBalancePolicy;
use Modules\HR\Policies\LeaveRequestPolicy;
use Modules\HR\Policies\LeaveTypePolicy;
use Modules\HR\Policies\RosterPolicy;
use Modules\HR\Policies\ShiftPolicy;
use Modules\HR\Services\EmployeeDirectoryService;
use Modules\HR\Services\LeaveApprovalHandler;
use Modules\HR\Services\NationalIdHasher;
use Modules\HR\Services\PayrollFactsService;
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

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        GrantLeaveCommand::class,
        SeedLeaveTypesCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(EmployeeDirectory::class, EmployeeDirectoryService::class);
        $this->app->singleton(PayrollFacts::class, PayrollFactsService::class);
        $this->app->singleton(NationalIdHasher::class, fn (): NationalIdHasher => new NationalIdHasher('employee-id|'.config('app.key')));
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['employee' => Employee::class, 'leave_request' => LeaveRequest::class]);

        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Designation::class, DesignationPolicy::class);
        Gate::policy(Grade::class, GradePolicy::class);
        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(Holiday::class, HolidayPolicy::class);
        Gate::policy(Roster::class, RosterPolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(LeaveType::class, LeaveTypePolicy::class);
        Gate::policy(LeaveRequest::class, LeaveRequestPolicy::class);
        Gate::policy(LeaveBalance::class, LeaveBalancePolicy::class);

        $this->app->make(DocumentNumbers::class)->register(new DocumentType('employee', 'Employee number', 'EMP', '{PREFIX}-{SEQ:4}', SequenceReset::Never));
        $this->app->make(DocumentNumbers::class)->register(new DocumentType('leave_request', 'Leave request', 'LV'));
        $this->app->make(ApprovalRegistry::class)->register(new ApprovalDocumentType(
            'leave_request', 'Leave request', 'An employee\'s request for leave, approved by HR; long leave also needs the General Manager. The amount is the number of days.',
            [new ApprovalStepDefinition('HR approver', 'hr.leave.approve', '0.00'), new ApprovalStepDefinition('General Manager', 'hr.leave.approve-long', '10.00')],
        ), LeaveApprovalHandler::class);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('hr:grant-leave')->dailyAt('01:30')->withoutOverlapping();
        });
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
            new PermissionDefinition('hr.leave.request', 'Ask for your own leave', DefaultRole::cases()),
            new PermissionDefinition('hr.leave.view', 'View everyone\'s leave requests, balances and the leave calendar', [...$managers, DefaultRole::PayrollOfficer]),
            new PermissionDefinition('hr.leave.manage', 'Ask for leave for others, cancel leave and adjust balances', $managers),
            new PermissionDefinition('hr.leave.approve', 'Approve leave requests', $managers),
            new PermissionDefinition('hr.leave.approve-long', 'Approve long leave (ten days or more)', [DefaultRole::GeneralManager]),
            new PermissionDefinition('hr.leave-type.manage', 'Change leave types and policies', $managers),
            new PermissionDefinition('hr.report.view', 'View the HR reports (headcount, attendance summary, leave balances)', $managers),
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
        $menu->add(new MenuItem('hr.leave', 'Leave requests', route: 'hr.leave.index', parent: 'hr', order: 60,
            permission: 'hr.leave.view', module: 'hr', active: ['hr.leave.index', 'hr.leave.show', 'hr.leave.create', 'hr.leave.store']));
        $menu->add(new MenuItem('hr.leave-calendar', 'Leave calendar', route: 'hr.leave.calendar', parent: 'hr', order: 62,
            permission: 'hr.leave.view', module: 'hr', active: 'hr.leave.calendar'));
        $menu->add(new MenuItem('hr.leave-balances', 'Leave balances', route: 'hr.leave.balances', parent: 'hr', order: 64,
            permission: 'hr.leave.view', module: 'hr', active: 'hr.leave.balances*'));
        $menu->add(new MenuItem('hr.my-leave', 'My leave', route: 'hr.leave.mine', parent: 'hr', order: 66,
            permission: 'hr.leave.request', module: 'hr', active: 'hr.leave.mine'));
        $menu->add(new MenuItem('hr.reports', 'Reports', route: 'hr.reports.index', parent: 'hr', order: 70,
            permission: 'hr.report.view', module: 'hr', active: 'hr.reports.*'));
        $menu->add(new MenuItem('hr.holidays', 'Holidays', route: 'hr.holidays.index', parent: 'hr', order: 50,
            permission: 'hr.holiday.view', module: 'hr', active: 'hr.holidays.*'));

        $menu->group('setup', 'Setup', 'bi-gear', order: 900);
        $menu->add(new MenuItem('hr.designations', 'Designations', route: 'hr.designations.index', parent: 'setup', order: 40,
            permission: 'hr.employee.view', module: 'hr', active: 'hr.designations.*'));
        $menu->add(new MenuItem('hr.shifts', 'Shifts', route: 'hr.shifts.index', parent: 'setup', order: 42,
            permission: 'hr.shift.view', module: 'hr', active: 'hr.shifts.*'));
        $menu->add(new MenuItem('hr.leave-types', 'Leave types', route: 'hr.leave-types.index', parent: 'setup', order: 43,
            permission: 'hr.leave.view', module: 'hr', active: 'hr.leave-types.*'));
        $menu->add(new MenuItem('hr.grades', 'Grades', route: 'hr.grades.index', parent: 'setup', order: 41,
            permission: 'hr.employee.view', module: 'hr', active: 'hr.grades.*'));
    }
}
