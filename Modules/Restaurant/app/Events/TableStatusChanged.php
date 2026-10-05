<?php

namespace Modules\Restaurant\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Restaurant\Broadcasting\RestaurantChannels;

/**
 * The outlet's floor changed (an order was opened, moved, merged, cancelled or added to): every POS
 * terminal of the outlet reloads its table tiles (Step 3.5). Broadcast only.
 */
class TableStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $tableIds
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $outletId,
        public readonly array $tableIds,
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
        return 'table.status';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['table_ids' => $this->tableIds];
    }
}
