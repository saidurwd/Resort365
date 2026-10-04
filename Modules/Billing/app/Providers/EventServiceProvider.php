<?php

namespace Modules\Billing\Providers;

use App\Support\Tenancy\Events\TenantCreated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Billing\Listeners\OpenGuestFolio;
use Modules\Billing\Listeners\SeedChargeCodes;
use Modules\Reservation\Events\ReservationCreated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        TenantCreated::class => [SeedChargeCodes::class],
        ReservationCreated::class => [OpenGuestFolio::class],
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
