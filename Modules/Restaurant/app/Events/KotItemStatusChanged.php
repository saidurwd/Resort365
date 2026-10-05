<?php

namespace Modules\Restaurant\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Restaurant\Broadcasting\RestaurantChannels;

/**
 * The kitchen moved a ticket on (ARCHITECTURE §4.5): its items are being prepared, ready to serve or
 * served. The outlet's POS screens refresh the order and, when ready, tell the waiter; the station's
 * other displays refresh their board.
 */
class KotItemStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $lineIds  the order lines on the ticket
     * @param  list<string>  $items  "2 × Chicken curry (Full)"
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $outletId,
        public readonly ?int $stationId,
        public readonly int $orderId,
        public readonly int $kotId,
        public readonly string $status,
        public readonly array $lineIds,
        public readonly array $items,
        public readonly string $orderNo,
        public readonly ?string $table,
        public readonly int $waiterId,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel(RestaurantChannels::outlet($this->tenantId, $this->outletId))];

        if ($this->stationId !== null) {
            $channels[] = new PrivateChannel(RestaurantChannels::kitchen($this->tenantId, $this->stationId));
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'kot.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId, 'kot_id' => $this->kotId, 'status' => $this->status, 'line_ids' => $this->lineIds, 'items' => $this->items,
            'order_no' => $this->orderNo, 'table' => $this->table, 'waiter_id' => $this->waiterId,
        ];
    }
}
