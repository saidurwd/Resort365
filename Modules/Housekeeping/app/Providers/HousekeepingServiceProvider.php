<?php

namespace Modules\Housekeeping\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Housekeeping\Console\DailyTasksCommand;
use Modules\Housekeeping\Console\PreventiveMaintenanceCommand;
use Modules\Housekeeping\Jobs\RunPreventiveMaintenance;
use Modules\Housekeeping\Models\HousekeepingTask;
use Modules\Housekeeping\Models\LostFoundItem;
use Modules\Housekeeping\Models\MaintenanceRequest;
use Modules\Housekeeping\Models\MaintenanceSchedule;
use Modules\Housekeeping\Models\RoomBlock;
use Modules\Housekeeping\Models\RoomStatusLog;
use Modules\Housekeeping\Policies\HousekeepingTaskPolicy;
use Modules\Housekeeping\Policies\LostFoundItemPolicy;
use Modules\Housekeeping\Policies\MaintenanceRequestPolicy;
use Modules\Housekeeping\Policies\MaintenanceSchedulePolicy;
use Modules\Housekeeping\Policies\RoomBlockPolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class HousekeepingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Housekeeping';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'housekeeping';

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
        DailyTasksCommand::class,
        PreventiveMaintenanceCommand::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'housekeeping_task' => HousekeepingTask::class,
            'room_block' => RoomBlock::class,
            'room_status_log' => RoomStatusLog::class,
            'maintenance_request' => MaintenanceRequest::class,
            'maintenance_schedule' => MaintenanceSchedule::class,
            'lost_found_item' => LostFoundItem::class,
        ]);
        Gate::policy(HousekeepingTask::class, HousekeepingTaskPolicy::class);
        Gate::policy(RoomBlock::class, RoomBlockPolicy::class);
        Gate::policy(MaintenanceRequest::class, MaintenanceRequestPolicy::class);
        Gate::policy(MaintenanceSchedule::class, MaintenanceSchedulePolicy::class);
        Gate::policy(LostFoundItem::class, LostFoundItemPolicy::class);

        $everyone = array_values(array_filter(DefaultRole::cases(), fn (DefaultRole $role): bool => ! in_array($role, [DefaultRole::TenantOwner, DefaultRole::Auditor], true)));
        $hk = DefaultRole::HousekeepingSupervisor;

        $this->app->make(PermissionRegistry::class)->register('Housekeeping', [
            new PermissionDefinition('housekeeping.board.view', 'View the room status board', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, $hk]),
            new PermissionDefinition('housekeeping.room.update', 'Change room cleaning status', [DefaultRole::FrontOfficeManager, $hk]),
            new PermissionDefinition('housekeeping.task.manage', 'Create, assign and inspect cleaning tasks', [$hk]),
            new PermissionDefinition('housekeeping.task.perform', 'Clean rooms (be given cleaning tasks)', [$hk]),
            new PermissionDefinition('housekeeping.block.manage', 'Take rooms out of order or out of service', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, $hk]),
            new PermissionDefinition('housekeeping.work-order.create', 'Report maintenance faults', $everyone),
            new PermissionDefinition('housekeeping.work-order.view', 'View all work orders', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, $hk, DefaultRole::MaintenanceTechnician]),
            new PermissionDefinition('housekeeping.work-order.manage', 'Assign and close any work order', [DefaultRole::GeneralManager, DefaultRole::MaintenanceTechnician]),
            new PermissionDefinition('housekeeping.schedule.manage', 'Plan preventive maintenance', [DefaultRole::GeneralManager, DefaultRole::MaintenanceTechnician]),
            new PermissionDefinition('housekeeping.lost-found.view', 'View lost & found', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, $hk]),
            new PermissionDefinition('housekeeping.lost-found.manage', 'Log and return lost & found items', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, $hk]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('housekeeping', 'Housekeeping', 'bi-stars', order: 150);
        $items = [
            ['housekeeping.board', 'Room status', 'housekeeping.board', 'housekeeping.board.view', ['housekeeping.board', 'housekeeping.board.*']],
            ['housekeeping.tasks', 'Tasks', 'housekeeping.tasks.index', 'housekeeping.task.manage', ['housekeeping.tasks.index', 'housekeeping.tasks.generate', 'housekeeping.tasks.assign']],
            ['housekeeping.my-tasks', 'My tasks', 'housekeeping.tasks.mine', 'housekeeping.task.perform', 'housekeeping.tasks.mine'],
            ['housekeeping.blocks', 'Out of order', 'housekeeping.blocks.index', 'housekeeping.block.manage', 'housekeeping.blocks.*'],
            ['housekeeping.work-orders', 'Work orders', 'housekeeping.work-orders.index', 'housekeeping.work-order.view', ['housekeeping.work-orders.index', 'housekeeping.work-orders.show']],
            ['housekeeping.report-fault', 'Report a fault', 'housekeeping.work-orders.create', 'housekeeping.work-order.create', 'housekeeping.work-orders.create'],
            ['housekeeping.schedules', 'Preventive maintenance', 'housekeeping.schedules.index', 'housekeeping.schedule.manage', 'housekeeping.schedules.*'],
            ['housekeeping.lost-found', 'Lost & found', 'housekeeping.lost-found.index', 'housekeeping.lost-found.view', 'housekeeping.lost-found.*'],
        ];

        foreach ($items as $order => [$key, $label, $route, $permission, $active]) {
            $menu->add(new MenuItem($key, $label, route: $route, parent: 'housekeeping', order: ($order + 1) * 10, permission: $permission, module: 'housekeeping', active: $active));
        }

        // Preventive maintenance: once a day, schedules due by each property's business date.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new RunPreventiveMaintenance)->name('housekeeping:preventive')->dailyAt('06:00')->withoutOverlapping();
        });
    }
}
