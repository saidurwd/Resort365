<?php

namespace Modules\Reports\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\DTOs\NotificationTemplateDefinition;
use Modules\Reports\Console\PurgeExportsCommand;
use Modules\Reports\DTOs\ReportDefinition;
use Modules\Reports\Models\ReportExport;
use Modules\Reports\Policies\ReportExportPolicy;
use Modules\Reports\Services\ReportRegistry;
use Modules\Reports\Services\Reports\CancellationReport;
use Modules\Reports\Services\Reports\ForecastReport;
use Modules\Reports\Services\Reports\LengthOfStayReport;
use Modules\Reports\Services\Reports\NationalityReport;
use Modules\Reports\Services\Reports\PaceReport;
use Modules\Reports\Services\Reports\RepeatGuestsReport;
use Modules\Reports\Services\Reports\SourceMixReport;
use Modules\Reports\Services\Reports\TopCompaniesReport;
use Modules\Reports\Services\Reports\TopGuestsReport;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ReportsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Reports';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'reports';

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
        PurgeExportsCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ReportRegistry::class);
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['report_export' => ReportExport::class]);
        Gate::policy(ReportExport::class, ReportExportPolicy::class);
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('reports:purge-exports')->dailyAt('03:30')->withoutOverlapping();
        });

        $templates = $this->app->make(NotificationTemplates::class);
        $templates->register(new NotificationTemplateDefinition('reports.export.ready', 'database', 'Your report is ready', 'The {format} file of "{report}" is ready to download. It is kept for a week.', ['report', 'format', 'reason'], 'Report file ready', 'In-app, to the person who asked for an Excel or PDF report.'));
        $templates->register(new NotificationTemplateDefinition('reports.export.failed', 'database', 'Your report could not be made', 'The {format} file of "{report}" could not be made: {reason}', ['report', 'format', 'reason'], 'Report file failed', 'In-app, to the person who asked for an Excel or PDF report, when it fails.'));

        $this->registerReports($this->app->make(ReportRegistry::class));
        $this->registerPermissions($this->app->make(PermissionRegistry::class));
        $this->registerMenu($this->app->make(MenuRegistry::class));
    }

    private function registerReports(ReportRegistry $registry): void
    {
        $registry->register(new ReportDefinition('forecast', __('Occupancy forecast'), 'rooms', __('Rooms booked against rooms to sell for the coming nights.'), 'reports.rooms.view', ['horizon']), ForecastReport::class);
        $registry->register(new ReportDefinition('pace', __('Booking pace'), 'rooms', __('How far ahead of arrival bookings are made.'), 'reports.rooms.view', ['range', 'by']), PaceReport::class);
        $registry->register(new ReportDefinition('sources', __('Booking sources'), 'rooms', __('Where bookings come from, with revenue and cancellations.'), 'reports.rooms.view', ['range', 'by']), SourceMixReport::class);
        $registry->register(new ReportDefinition('cancellations', __('Cancellations and no-shows'), 'rooms', __('Cancellation rate, no-shows and fees by source.'), 'reports.rooms.view', ['range', 'by']), CancellationReport::class);
        $registry->register(new ReportDefinition('length-of-stay', __('Length of stay'), 'rooms', __('How long guests stay, and the average stay.'), 'reports.rooms.view', ['range', 'by']), LengthOfStayReport::class);
        $registry->register(new ReportDefinition('nationalities', __('Nationality mix'), 'guests', __('Where guests come from.'), 'reports.guests.view', ['range']), NationalityReport::class);
        $registry->register(new ReportDefinition('repeat-guests', __('Repeat guests'), 'guests', __('Guests who have stayed more than once.'), 'reports.guests.view', ['range', 'limit']), RepeatGuestsReport::class);
        $registry->register(new ReportDefinition('top-guests', __('Top guests'), 'guests', __('The guests who spent most.'), 'reports.guests.view', ['range', 'limit']), TopGuestsReport::class);
        $registry->register(new ReportDefinition('top-companies', __('Top companies'), 'guests', __('The companies whose bookings brought the most.'), 'reports.guests.view', ['range', 'limit']), TopCompaniesReport::class);
    }

    private function registerPermissions(PermissionRegistry $permissions): void
    {
        $managers = [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::Accountant];

        $permissions->register('Reports', [
            new PermissionDefinition('reports.dashboard.view', 'See the management dashboard and the reports hub', [...$managers, DefaultRole::FnbManager]),
            new PermissionDefinition('reports.rooms.view', 'View the rooms reports (forecast, pace, sources, cancellations, length of stay)', $managers),
            new PermissionDefinition('reports.guests.view', 'View the guest reports (nationality mix, repeat guests, top guests and companies)', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager]),
            new PermissionDefinition('reports.report.export', 'Ask for reports as Excel or PDF files', $managers),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('reports', 'Reports', 'bi-bar-chart-line', order: 430);
        $menu->add(new MenuItem('reports.hub', 'All reports', route: 'reports.index', parent: 'reports', order: 10,
            permission: 'reports.dashboard.view', module: 'reports', active: ['reports.index', 'reports.show']));
        $menu->add(new MenuItem('reports.files', 'My files', route: 'reports.exports.index', parent: 'reports', order: 20,
            permission: 'reports.report.export', module: 'reports', active: 'reports.exports.*'));
    }
}
