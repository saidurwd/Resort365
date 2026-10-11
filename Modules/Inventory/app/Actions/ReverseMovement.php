<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\DTOs\StockMovementSummary;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\StockMovement;

/**
 * Undoes a stock movement with an opposite one at the cost the original had (Step 5.3). A movement is reversed once, and
 * a reversal cannot itself be reversed (record the movement again instead).
 */
class ReverseMovement extends Action
{
    public function __construct(private readonly StockMovementContract $stock) {}

    /**
     * @throws StockRuleViolated
     */
    public function handle(StockMovement $movement, string $reason, ?int $userId = null): StockMovementSummary
    {
        if (trim($reason) === '') {
            throw new StockRuleViolated(__('Give a reason for the reversal.'));
        }

        return $this->transaction(function () use ($movement, $reason, $userId): StockMovementSummary {
            $original = StockMovement::query()->lockForUpdate()->findOrFail($movement->id);

            if ($original->movement_type === MovementType::Reversal || StockMovement::query()->where('reverses_id', $original->id)->exists()) {
                throw new StockRuleViolated(__('This movement was reversed already, or is a reversal itself.'));
            }

            $wasIn = (float) $original->quantity > 0;

            return $this->stock->record(new NewStockMovement(
                MovementType::Reversal, $original->store_id, $original->item_id, ltrim((string) $original->quantity, '-'), (string) $original->unit_cost, reason: 'reversal', notes: $reason,
                sourceType: 'stock_movement', sourceId: $original->id, sourceEvent: 'reversed', userId: $userId, reverses: $original->id, adds: ! $wasIn,
            ));
        });
    }
}
