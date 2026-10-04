<?php

namespace Modules\FrontOffice\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\NotificationTemplateDefinition;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\FrontOffice\Console\NightAuditCommand;
use Modules\FrontOffice\Jobs\RunDueNightAudits;
use Modules\FrontOffice\Models\DailyStatistic;
use Modules\FrontOffice\Models\NightAudit;
use Modules\FrontOffice\Policies\NightAuditPolicy;
use Modules\FrontOffice\Services\FrontDeskTab;
use Modules\Reservation\Contracts\ReservationTabs;
use Modules\Reservation\DTOs\ReservationTab;
use Nwidart\Modules\Support\ModuleServiceProvider;

class FrontOfficeServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'FrontOffice';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'frontoffice';

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
        NightAuditCommand::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['night_audit' => NightAudit::class, 'daily_statistic' => DailyStatistic::class]);
        Gate::policy(NightAudit::class, NightAuditPolicy::class);

        $this->app->make(PermissionRegistry::class)->register('Front office', [
            new PermissionDefinition('frontoffice.desk.view', 'View the front desk', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::ReservationAgent]),
            new PermissionDefinition('frontoffice.checkin.perform', 'Check guests in', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent]),
            new PermissionDefinition('frontoffice.checkout.perform', 'Check guests out', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent]),
            new PermissionDefinition('frontoffice.stay.change', 'Move rooms, extend or shorten stays', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent]),
            new PermissionDefinition('frontoffice.stay.reprice', 'Charge the new room\'s rate on a room move', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager]),
            new PermissionDefinition('frontoffice.audit.view', 'View night audits', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::Accountant]),
            new PermissionDefinition('frontoffice.audit.run', 'Run the night audit', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager]),
            new PermissionDefinition('frontoffice.report.view', 'View the daily flash report', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::Accountant]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('frontoffice', 'Front Office', 'bi-door-open', order: 100);
        $menu->add(new MenuItem('frontoffice.desk', 'Front Desk', route: 'frontoffice.desk', parent: 'frontoffice', order: 10,
            permission: 'frontoffice.desk.view', module: 'frontoffice', active: ['frontoffice.desk', 'frontoffice.check-in.*', 'frontoffice.check-out.*', 'frontoffice.stay.*']));
        $menu->add(new MenuItem('frontoffice.night-audit', 'Night audit', route: 'frontoffice.night-audit.index', parent: 'frontoffice', order: 20,
            permission: 'frontoffice.audit.view', module: 'frontoffice', active: 'frontoffice.night-audit.*'));
        $menu->add(new MenuItem('frontoffice.flash', 'Flash report', route: 'frontoffice.reports.flash', parent: 'frontoffice', order: 30,
            permission: 'frontoffice.report.view', module: 'frontoffice', active: 'frontoffice.reports.flash'));

        $this->app->make(Settings::class)->define(new SettingDefinition('frontoffice.auto_night_audit', 'Run the night audit automatically', SettingType::Boolean, true,
            SettingScope::Property, 'Operations', help: 'At the night audit time, when nothing blocks it (departures still in house). Needs the scheduler (schedule:run every minute).'));
        $this->app->make(NotificationTemplates::class)->register(new NotificationTemplateDefinition('frontoffice.night_audit_blocked', 'database',
            'Night audit of {date} not run', 'The scheduled night audit of {property} for {date} could not run: {reasons}', ['property', 'date', 'reasons'],
            'Night audit blocked', 'In-app, to the people who run the night audit, when the scheduled audit could not run.'));

        // The scheduled night audit; each property runs at its own night audit time.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new RunDueNightAudits)->name('frontoffice:night-audit')->everyFiveMinutes()->withoutOverlapping();
        });

        // The booking page's Front desk tab (stay status, check-in, registration card).
        $this->app->make(ReservationTabs::class)->add(new ReservationTab('frontdesk', 'Front desk', 'bi-door-open', 'frontoffice.desk.view', 'frontoffice',
            fn (int $reservationId) => $this->app->make(FrontDeskTab::class)->render($reservationId), order: 5));
    }
}
