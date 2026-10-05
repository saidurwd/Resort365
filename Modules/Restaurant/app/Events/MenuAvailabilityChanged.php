<?php

namespace Modules\Restaurant\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Restaurant\Broadcasting\RestaurantChannels;

/**
 * An outlet's menu changed (an item 86'd or back on sale, or the price list saved): its POS terminals
 * reload the menu (Step 3.5). Broadcast only.
 */
class MenuAvailabilityChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $outletId,
        public readonly ?string $message = null,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(RestaurantChannels::outlet($this->tenantId, $this->outletId))];
    }

    public function broadcastAs(): string
    {
        return 'menu.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
