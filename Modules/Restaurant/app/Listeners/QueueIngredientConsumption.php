<?php

namespace Modules\Restaurant\Listeners;

use Modules\Restaurant\Events\KotItemVoided;
use Modules\Restaurant\Events\RestaurantBillSettled;
use Modules\Restaurant\Events\RestaurantBillVoided;
use Modules\Restaurant\Jobs\ConsumeBillIngredients;
use Modules\Restaurant\Jobs\ReverseBillIngredients;
use Modules\Restaurant\Jobs\WriteOffVoidedLine;

/**
 * Queues the stock side of a sale (Step 5.7): ingredients out when a bill settles, back when it is voided before the food
 * was made, and written off when a line is voided after it was.
 */
class QueueIngredientConsumption
{
    public function onSettled(RestaurantBillSettled $event): void
    {
        ConsumeBillIngredients::dispatch($event->billId);
    }

    public function onVoided(RestaurantBillVoided $event): void
    {
        if (! $event->foodPrepared) {
            ReverseBillIngredients::dispatch($event->billId);
        }
    }

    public function onLineVoided(KotItemVoided $event): void
    {
        if ($event->wastage) {
            WriteOffVoidedLine::dispatch($event->orderLineId);
        }
    }
}
