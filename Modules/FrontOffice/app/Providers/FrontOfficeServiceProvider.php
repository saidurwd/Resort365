<?php

namespace Modules\FrontOffice\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
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

    public function boot(): void
    {
        parent::boot();

        $this->app->make(PermissionRegistry::class)->register('Front office', [
            new PermissionDefinition('frontoffice.desk.view', 'View the front desk', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::ReservationAgent]),
            new PermissionDefinition('frontoffice.checkin.perform', 'Check guests in', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('frontoffice', 'Front Office', 'bi-door-open', order: 100);
        $menu->add(new MenuItem('frontoffice.desk', 'Front Desk', route: 'frontoffice.desk', parent: 'frontoffice', order: 10,
            permission: 'frontoffice.desk.view', module: 'frontoffice', active: ['frontoffice.desk', 'frontoffice.check-in.*']));

        // The booking page's Front desk tab (stay status, check-in, registration card).
        $this->app->make(ReservationTabs::class)->add(new ReservationTab('frontdesk', 'Front desk', 'bi-door-open', 'frontoffice.desk.view', 'frontoffice',
            fn (int $reservationId) => $this->app->make(FrontDeskTab::class)->render($reservationId), order: 5));
    }
}
