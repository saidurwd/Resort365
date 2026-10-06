<?php

namespace Modules\Restaurant\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Restaurant\Broadcasting\RestaurantChannels;

/**
 * A room-service or location-delivery order moved on (ARCHITECTURE §5.10.4): the outlet's POS screens
 * refresh their list of deliveries. Broadcast only (Step 3.8).
 */
class DeliveryStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $outletId,
        public readonly int $orderId,
        public readonly string $status,
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
        return 'delivery.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId, 'status' => $this->status];
    }
}
