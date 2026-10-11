<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\AdjustmentReason;
use Modules\Inventory\Enums\CountStatus;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Events\StockAdjusted;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\StockCountLine;
use Modules\Inventory\Services\CountVariance;

/**
 * Completes a count (Step 5.4): each counted item whose count differs from what it should have shown is adjusted, a gain
 * in and a loss out, at the average cost frozen on the sheet, in one transaction. Items not counted are left alone.
 */
class CompleteStockCount extends Action
{
    public function __construct(
        private readonly StockMovementContract $stock,
        private readonly CountVariance $variances,
    ) {}

    /**
     * @return int the number of adjustments made
     *
     * @throws StockRuleViolated
     */
    public function handle(StockCount $count, ?int $userId = null): int
    {
        return $this->transaction(function () use ($count, $userId): int {
            $locked = StockCount::query()->lockForUpdate()->findOrFail($count->id);

            if ($locked->status !== CountStatus::Counting) {
                throw new StockRuleViolated(__('Count :no is :status.', ['no' => $locked->count_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $lines = StockCountLine::query()->where('stock_count_id', $locked->id)->get();

            if ($lines->every(fn (StockCountLine $line): bool => $line->counted_quantity === null)) {
                throw new StockRuleViolated(__('Enter what was counted first.'));
            }

            $adjusted = 0;

            foreach ($lines as $line) {
                $variance = $this->variances->variance($line, $locked->store_id);

                if ($variance === null) {
                    continue;
                }

                $line->forceFill(['variance_quantity' => $variance])->save();

                if (BigDecimal::of($variance)->isZero()) {
                    continue;
                }

                $gain = BigDecimal::of($variance)->isPositive();
                $movement = $this->stock->record(new NewStockMovement(
                    $gain ? MovementType::AdjustmentIn : MovementType::AdjustmentOut, $locked->store_id, $line->item_id, ltrim($variance, '-'), (string) $line->unit_cost, reason: AdjustmentReason::CountVariance->value,
                    notes: $locked->count_no, sourceType: 'stock_count', sourceId: $locked->id, sourceEvent: 'counted', idempotencyKey: 'count:'.$locked->id.':'.$line->item_id, userId: $userId,
                ));
                $line->forceFill(['adjustment_movement_id' => $movement->id])->save();
                $adjusted++;
            }

            $locked->forceFill(['status' => CountStatus::Completed, 'completed_at' => now(), 'completed_by' => $userId])->save();

            if ($adjusted > 0) {
                StockAdjusted::dispatch($locked->tenant_id, $locked->property_id, 'stock_count', $locked->id);
            }

            return $adjusted;
        }, attempts: 3);
    }
}
