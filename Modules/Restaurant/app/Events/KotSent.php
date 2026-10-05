<?php

namespace Modules\Restaurant\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Restaurant\Broadcasting\RestaurantChannels;

/**
 * Kitchen tickets were sent (ARCHITECTURE §4.5): the station's kitchen display shows them. Only ids go
 * out; the display reloads its board from the server. Tickets of lines without a station reach no display.
 */
class KotSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $kotIds
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $outletId,
        public readonly ?int $stationId,
        public readonly int $orderId,
        public readonly array $kotIds,
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
        return 'kot.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['order_id' => $this->orderId, 'kot_ids' => $this->kotIds];
    }
}
