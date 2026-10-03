<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Modules\Rates\Models\Promotion;

/**
 * Deletes (soft) a promotion; bookings that used it keep their discount.
 */
class DeletePromotion extends Action
{
    public function handle(Promotion $promotion): void
    {
        $promotion->delete();
    }
}
