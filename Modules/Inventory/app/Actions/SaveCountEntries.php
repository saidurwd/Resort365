<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Inventory\Enums\CountStatus;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\StockCountLine;

/**
 * Saves what was counted (Step 5.4); an empty entry means "not counted yet". Can be done in several goes.
 */
class SaveCountEntries extends Action
{
    /**
     * @param  array<int, string|null>  $entries  item id => counted quantity
     *
     * @throws StockRuleViolated
     */
    public function handle(StockCount $count, array $entries): int
    {
        if ($count->status !== CountStatus::Counting) {
            throw new StockRuleViolated(__('Count :no is :status.', ['no' => $count->count_no, 'status' => mb_strtolower($count->status->label())]));
        }

        return $this->transaction(function () use ($count, $entries): int {
            $saved = 0;

            foreach (StockCountLine::query()->where('stock_count_id', $count->id)->get() as $line) {
                if (! array_key_exists($line->item_id, $entries)) {
                    continue;
                }

                $value = $entries[$line->item_id];

                if ($value !== null && $value !== '' && BigDecimal::of($value)->isNegative()) {
                    throw new StockRuleViolated(__('A counted quantity cannot be negative.'));
                }

                $line->forceFill(['counted_quantity' => $value === null || $value === '' ? null : (string) BigDecimal::of($value)->toScale(4)])->save();
                $saved++;
            }

            return $saved;
        });
    }
}
