<?php

namespace Modules\Reservation\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\Property\Contracts\RoomUsage;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Services\LockedRoomUsage;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ReservationServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Reservation';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'reservation';

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

        // Replaces Property's "no bookings" default: rooms with future locks cannot be deleted.
        $this->app->singleton(RoomUsage::class, LockedRoomUsage::class);
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['inventory_lock' => InventoryLock::class]);

        $this->app->make(PermissionRegistry::class)->register('Reservations', [
            new PermissionDefinition('reservation.availability.view', 'Search availability and prices', [
                DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::ReservationAgent,
            ]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('reservations', 'Reservations', 'bi-calendar-check', order: 200);
        $menu->add(new MenuItem('reservation.availability', 'Availability', route: 'reservation.availability', parent: 'reservations', order: 10,
            permission: 'reservation.availability.view', module: 'reservation', active: 'reservation.availability'));

        $this->app->make(Settings::class)->define(new SettingDefinition('reservation.whole_cottage_discount_percent', 'Whole-cottage discount %',
            SettingType::Decimal, '0', SettingScope::Property, 'Rates',
            help: 'Taken off the sum of the room rates when a whole cottage has no cottage-type rate for a night.', rules: ['min:0', 'max:100']));
    }
}
