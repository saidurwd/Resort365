<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Enums\CountStatus;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\StockCount;

/**
 * Drops a count that is still open (Step 5.4); nothing was adjusted.
 */
class CancelStockCount extends Action
{
    /**
     * @throws StockRuleViolated
     */
    public function handle(StockCount $count): StockCount
    {
        return $this->transaction(function () use ($count): StockCount {
            $locked = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if ($locked->status !== CountStatus::Counting) {
                throw new StockRuleViolated(__('Count :no is :status.', ['no' => $locked->count_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $locked->forceFill(['status' => CountStatus::Cancelled])->save();

            return $locked;
        });
    }
}
