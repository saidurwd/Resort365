<?php

namespace Modules\Restaurant\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Restaurant\Broadcasting\RestaurantChannels;

/**
 * An item sent to the kitchen was voided (ARCHITECTURE §4.5): the station's display shows the void
 * ticket and strikes the line through on the original one.
 */
class KotItemVoided implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $outletId,
        public readonly ?int $stationId,
        public readonly int $orderId,
        public readonly int $orderLineId,
        public readonly int $voidKotId,
        public readonly bool $wastage,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return $this->stationId === null ? [] : [new PrivateChannel(RestaurantChannels::kitchen($this->tenantId, $this->stationId))];
    }

    public function broadcastAs(): string
    {
        return 'kot.voided';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId, 'line_id' => $this->orderLineId, 'kot_id' => $this->voidKotId];
    }
}
