<?php

namespace Modules\Reservation\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\Property\Contracts\RoomUsage;
use Modules\Rates\Contracts\RatePlanUsage;
use Modules\Reservation\Console\ExpireHoldsCommand;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Contracts\ReservationTabs;
use Modules\Reservation\Jobs\ExpireTentativeHolds;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Models\ReservationItemNight;
use Modules\Reservation\Models\ReservationLog;
use Modules\Reservation\Policies\ReservationPolicy;
use Modules\Reservation\Services\BookedRatePlanUsage;
use Modules\Reservation\Services\LockedRoomUsage;
use Modules\Reservation\Services\ReservationLookupService;
use Modules\Reservation\Services\ReservationTabRegistry;
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
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ExpireHoldsCommand::class,
    ];

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
        // Replaces Rates' default: plans that upcoming bookings use cannot be deleted.
        $this->app->singleton(RatePlanUsage::class, BookedRatePlanUsage::class);
        $this->app->bind(ReservationLookup::class, ReservationLookupService::class);
        $this->app->singleton(ReservationTabs::class, ReservationTabRegistry::class);
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'inventory_lock' => InventoryLock::class,
            'reservation' => Reservation::class,
            'reservation_item' => ReservationItem::class,
            'reservation_item_night' => ReservationItemNight::class,
            'reservation_guest' => ReservationGuest::class,
            'reservation_log' => ReservationLog::class,
        ]);
        Gate::policy(Reservation::class, ReservationPolicy::class);

        $desk = [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::ReservationAgent];

        $this->app->make(PermissionRegistry::class)->register('Reservations', [
            new PermissionDefinition('reservation.availability.view', 'Search availability and prices', $desk),
            new PermissionDefinition('reservation.booking.view', 'View reservations', $desk),
            new PermissionDefinition('reservation.booking.create', 'Create reservations', $desk),
            new PermissionDefinition('reservation.booking.update', 'Change reservations (stay, guests, deposit)', $desk),
            new PermissionDefinition('reservation.booking.cancel', 'Cancel reservations', $desk),
            new PermissionDefinition('reservation.deposit.override', 'Allow a deposit outside the deposit policy', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('reservations', 'Reservations', 'bi-calendar-check', order: 200);
        $menu->add(new MenuItem('reservation.new-booking', 'New booking', route: 'reservation.bookings.create', parent: 'reservations', order: 5,
            permission: 'reservation.booking.create', module: 'reservation', active: ['reservation.bookings.create*', 'reservation.bookings.choose*', 'reservation.bookings.guest', 'reservation.bookings.guest.store',
                'reservation.bookings.pricing*', 'reservation.bookings.confirm', 'reservation.bookings.store', 'reservation.bookings.reset']));
        $menu->add(new MenuItem('reservation.bookings', 'Reservations', route: 'reservation.bookings.index', parent: 'reservations', order: 7,
            permission: 'reservation.booking.view', module: 'reservation', active: ['reservation.bookings.index', 'reservation.bookings.show', 'reservation.bookings.edit', 'reservation.bookings.review',
                'reservation.bookings.update', 'reservation.bookings.deposit', 'reservation.bookings.guests.*', 'reservation.bookings.cancel*']));
        $menu->add(new MenuItem('reservation.availability', 'Availability', route: 'reservation.availability', parent: 'reservations', order: 10,
            permission: 'reservation.availability.view', module: 'reservation', active: 'reservation.availability'));

        $this->app->make(Settings::class)->define(new SettingDefinition('reservation.whole_cottage_discount_percent', 'Whole-cottage discount %',
            SettingType::Decimal, '0', SettingScope::Property, 'Rates',
            help: 'Taken off the sum of the room rates when a whole cottage has no cottage-type rate for a night.', rules: ['min:0', 'max:100']));

        // Hold expiry (ARCHITECTURE §6.5 rule 4): unpaid tentative bookings past their deposit due time.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new ExpireTentativeHolds)->name('reservation:expire-holds')->everyFiveMinutes()->withoutOverlapping();
        });
    }
}
