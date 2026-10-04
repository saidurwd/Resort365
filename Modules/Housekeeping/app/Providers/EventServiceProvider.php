<?php

namespace Modules\Housekeeping\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\FrontOffice\Events\GuestCheckedOut;
use Modules\FrontOffice\Events\NightAuditCompleted;
use Modules\Housekeeping\Listeners\CreateTasksAfterNightAudit;
use Modules\Housekeeping\Listeners\PrepareRoomAfterMove;
use Modules\Housekeeping\Listeners\PrepareRoomsAfterCheckOut;
use Modules\Reservation\Events\RoomMoved;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Housekeeping reacts to the guest journey (ARCHITECTURE §4.5).
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        GuestCheckedOut::class => [PrepareRoomsAfterCheckOut::class],
        RoomMoved::class => [PrepareRoomAfterMove::class],
        NightAuditCompleted::class => [CreateTasksAfterNightAudit::class],
    ];

    /**
     * Listeners are registered above, not discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
