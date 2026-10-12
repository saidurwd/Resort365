<?php

namespace Modules\Restaurant\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\FrontOffice\Events\NightAuditCompleted;
use Modules\Restaurant\Events\KotItemVoided;
use Modules\Restaurant\Events\RestaurantBillSettled;
use Modules\Restaurant\Events\RestaurantBillVoided;
use Modules\Restaurant\Listeners\QueueIngredientConsumption;
use Modules\Restaurant\Listeners\SnapshotMealsAfterNightAudit;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        NightAuditCompleted::class => [SnapshotMealsAfterNightAudit::class],
        RestaurantBillSettled::class => [QueueIngredientConsumption::class.'@onSettled'],
        RestaurantBillVoided::class => [QueueIngredientConsumption::class.'@onVoided'],
        KotItemVoided::class => [QueueIngredientConsumption::class.'@onLineVoided'],
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
