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
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\NotificationTemplateDefinition;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\Property\Contracts\RoomUsage;
use Modules\Rates\Contracts\RatePlanUsage;
use Modules\Reservation\Console\ExpireHoldsCommand;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Contracts\ReservationTabs;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\Enums\GuestEmail;
use Modules\Reservation\Jobs\ExpireTentativeHolds;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\QuoteItem;
use Modules\Reservation\Models\QuoteItemNight;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Models\ReservationItemNight;
use Modules\Reservation\Models\ReservationLog;
use Modules\Reservation\Policies\QuotePolicy;
use Modules\Reservation\Policies\ReservationPolicy;
use Modules\Reservation\Services\BookedRatePlanUsage;
use Modules\Reservation\Services\LockedRoomUsage;
use Modules\Reservation\Services\ReservationLookupService;
use Modules\Reservation\Services\ReservationTabRegistry;
use Modules\Reservation\Services\StayOperationsService;
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
        $this->app->bind(StayOperations::class, StayOperationsService::class);
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
            'quote' => Quote::class,
            'quote_item' => QuoteItem::class,
            'quote_item_night' => QuoteItemNight::class,
        ]);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);

        $desk = [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::ReservationAgent];

        $this->app->make(PermissionRegistry::class)->register('Reservations', [
            new PermissionDefinition('reservation.availability.view', 'Search availability and prices', $desk),
            new PermissionDefinition('reservation.booking.view', 'View reservations', $desk),
            new PermissionDefinition('reservation.booking.create', 'Create reservations', $desk),
            new PermissionDefinition('reservation.booking.update', 'Change reservations (stay, guests, deposit)', $desk),
            new PermissionDefinition('reservation.booking.cancel', 'Cancel reservations', $desk),
            new PermissionDefinition('reservation.quote.view', 'View quotes', $desk),
            new PermissionDefinition('reservation.quote.create', 'Make, send and book quotes', $desk),
            new PermissionDefinition('reservation.report.view', 'View booking reports', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::ReservationAgent]),
            new PermissionDefinition('reservation.deposit.override', 'Allow a deposit outside the deposit policy', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('reservations', 'Reservations', 'bi-calendar-check', order: 200);
        $menu->add(new MenuItem('reservation.new-booking', 'New booking', route: 'reservation.bookings.create', parent: 'reservations', order: 5,
            permission: 'reservation.booking.create', module: 'reservation', active: ['reservation.bookings.create*', 'reservation.bookings.choose*', 'reservation.bookings.guest', 'reservation.bookings.guest.store',
                'reservation.bookings.pricing*', 'reservation.bookings.confirm', 'reservation.bookings.store', 'reservation.bookings.quote', 'reservation.bookings.reset']));
        $menu->add(new MenuItem('reservation.bookings', 'Reservations', route: 'reservation.bookings.index', parent: 'reservations', order: 7,
            permission: 'reservation.booking.view', module: 'reservation', active: ['reservation.bookings.index', 'reservation.bookings.show', 'reservation.bookings.edit', 'reservation.bookings.review',
                'reservation.bookings.update', 'reservation.bookings.deposit', 'reservation.bookings.guests.*', 'reservation.bookings.cancel*', 'reservation.bookings.voucher']));
        $menu->add(new MenuItem('reservation.quotes', 'Quotes', route: 'reservation.quotes.index', parent: 'reservations', order: 8,
            permission: 'reservation.quote.view', module: 'reservation', active: 'reservation.quotes.*'));
        $menu->add(new MenuItem('reservation.report.sources', 'Booking sources', route: 'reservation.reports.sources', parent: 'reservations', order: 20,
            permission: 'reservation.report.view', module: 'reservation', active: 'reservation.reports.sources'));
        $menu->add(new MenuItem('reservation.availability', 'Availability', route: 'reservation.availability', parent: 'reservations', order: 10,
            permission: 'reservation.availability.view', module: 'reservation', active: 'reservation.availability'));

        $settings = $this->app->make(Settings::class);
        $settings->define(new SettingDefinition('reservation.whole_cottage_discount_percent', 'Whole-cottage discount %',
            SettingType::Decimal, '0', SettingScope::Property, 'Rates',
            help: 'Taken off the sum of the room rates when a whole cottage has no cottage-type rate for a night.', rules: ['min:0', 'max:100']));
        $settings->define(new SettingDefinition('reservation.payment_instructions', 'How guests pay the deposit', SettingType::Text,
            'Pay by bank transfer or mobile wallet quoting your booking number, or call us to pay by card.', SettingScope::Property, 'Reservations',
            help: 'Sent in the booking email with the deposit and its due time.', rules: ['max:1000']));
        $settings->define(new SettingDefinition('reservation.quote_valid_days', 'Quotes valid for (days)', SettingType::Integer, 7, SettingScope::Property,
            'Reservations', help: 'Quoted prices are held this many days.', rules: ['min:1', 'max:365']));

        $this->registerTemplates($this->app->make(NotificationTemplates::class));

        // Hold expiry (ARCHITECTURE §6.5 rule 4): unpaid tentative bookings past their deposit due time.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new ExpireTentativeHolds)->name('reservation:expire-holds')->everyFiveMinutes()->withoutOverlapping();
        });
    }

    /**
     * The guest emails of ARCHITECTURE §5.6 and the staff notice of an expired hold (editable in
     * Setup → Email templates). The first line of an email is its greeting.
     */
    private function registerTemplates(NotificationTemplates $templates): void
    {
        $booking = ['guest', 'code', 'property', 'check_in', 'check_out', 'nights', 'rooms', 'currency', 'total', 'deposit', 'deposit_due',
            'paid', 'balance', 'payment_instructions', 'check_in_time', 'check_out_time', 'property_phone', 'property_email'];

        foreach ([
            new NotificationTemplateDefinition(GuestEmail::BookingCreated->value, 'mail', 'Your booking {code} at {property}',
                "Dear {guest},\nThank you for booking with {property}. We are holding {rooms} for you from {check_in} to {check_out} ({nights} nights).\nTotal: {currency} {total}. To confirm the booking, please pay the deposit of {currency} {deposit} by {deposit_due}.\n{payment_instructions}\nIf the deposit is not received by then, the rooms are released.",
                $booking, 'Booking received', 'To the guest, when a booking waits for its deposit.'),
            new NotificationTemplateDefinition(GuestEmail::BookingConfirmed->value, 'mail', 'Booking {code} confirmed',
                "Dear {guest},\nYour booking {code} at {property} is confirmed: {rooms}, {check_in} to {check_out} ({nights} nights).\nPaid so far: {currency} {paid}. Balance: {currency} {balance}.\nCheck-in is from {check_in_time} and check-out by {check_out_time}. Your confirmation voucher is attached.\nWe look forward to welcoming you.",
                $booking, 'Booking confirmed', 'To the guest, when the deposit is paid or waived (with the voucher).'),
            new NotificationTemplateDefinition(GuestEmail::BookingCancelled->value, 'mail', 'Booking {code} cancelled',
                "Dear {guest},\nYour booking {code} at {property} for {check_in} to {check_out} has been cancelled.\nCancellation fee: {currency} {fee}. Refund due to you: {currency} {refund}.\nWe hope to welcome you another time.",
                [...$booking, 'fee', 'refund'], 'Booking cancelled', 'To the guest, when a booking is cancelled.'),
            new NotificationTemplateDefinition(GuestEmail::HoldExpired->value, 'mail', 'Booking {code} released',
                "Dear {guest},\nWe did not receive the deposit for booking {code} ({check_in} to {check_out}) in time, so the rooms have been released.\nIf you would still like to stay with us, please contact {property} at {property_phone} and we will gladly book again.",
                $booking, 'Booking released', 'To the guest, when the deposit was not paid in time.'),
            new NotificationTemplateDefinition(GuestEmail::Quote->value, 'mail', 'Your quotation {code} from {property}',
                "Dear {guest},\nThank you for your interest in {property}. Please find our quotation {code} attached: {rooms}, {check_in} to {check_out} ({nights} nights), {currency} {total} including taxes.\nA deposit of {currency} {deposit} confirms the booking. These prices are held until {valid_until}.\nReply to this email or call us to book.",
                ['guest', 'code', 'property', 'check_in', 'check_out', 'nights', 'rooms', 'currency', 'total', 'deposit', 'valid_until', 'property_phone', 'property_email'],
                'Quotation', 'To the guest, when a quote is emailed (with the quotation PDF).'),
            new NotificationTemplateDefinition('reservation.hold_expired_staff', 'database', 'Booking {code} released',
                "The deposit for {guest}'s booking {code} was not paid in time, so the booking was cancelled and its rooms released.",
                ['code', 'guest'], 'Hold expired (staff)', 'In-app, to the staff member who made the booking.'),
        ] as $definition) {
            $templates->register($definition);
        }
    }
}
