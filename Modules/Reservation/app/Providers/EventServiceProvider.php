<?php

namespace Modules\Reservation\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Billing\Events\PaymentReceived;
use Modules\Billing\Events\RefundIssued;
use Modules\Guest\Events\GuestsMerged;
use Modules\Reservation\Events\ReservationCancelled;
use Modules\Reservation\Events\ReservationConfirmed;
use Modules\Reservation\Events\ReservationCreated;
use Modules\Reservation\Listeners\ApplyReceivedPayment;
use Modules\Reservation\Listeners\ApplyRefund;
use Modules\Reservation\Listeners\MoveReservationsToKeptGuest;
use Modules\Reservation\Listeners\SendBookingNotifications;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        PaymentReceived::class => [ApplyReceivedPayment::class],
        RefundIssued::class => [ApplyRefund::class],
        GuestsMerged::class => [MoveReservationsToKeptGuest::class],
        ReservationCreated::class => [SendBookingNotifications::class.'@created'],
        ReservationConfirmed::class => [SendBookingNotifications::class.'@confirmed'],
        ReservationCancelled::class => [SendBookingNotifications::class.'@cancelled'],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Discover listeners in this module only (Laravel's default is the application's app/Listeners).
     *
     * @return array<int, string>
     */
    protected function discoverEventsWithin(): array
    {
        return [__DIR__.'/../Listeners'];
    }

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
