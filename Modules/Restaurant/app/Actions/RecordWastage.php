<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\Contracts\StoreDirectory;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\DTOs\StoreSummary;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Restaurant\DTOs\NewWastage;
use Modules\Restaurant\Events\WastageRecorded;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\WastageEntry;
use Modules\Restaurant\Models\WastageLine;
use Modules\Restaurant\Services\RecipeBook;

/**
 * Writes off spoiled, dropped or surplus food (Step 5.7): raw ingredients, or portions of a dish expanded through its
 * recipe, taken out of the store as `wastage` at cost, with a reason and a number. Fires `WastageRecorded` for the ledger.
 */
class RecordWastage extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly StockMovementContract $stock,
        private readonly StoreDirectory $stores,
        private readonly RecipeBook $book,
    ) {}

    /**
     * @throws RestaurantSetupInvalid
     */
    public function handle(NewWastage $data): WastageEntry
    {
        $store = $this->stores->find($data->storeId);

        if (! $store instanceof StoreSummary) {
            throw new RestaurantSetupInvalid(__('Choose the store the food was in.'));
        }

        $quantities = [];

        foreach ($data->items as $row) {
            if (BigDecimal::of($row['quantity'])->isPositive()) {
                $quantities[$row['item_id']] = bcadd($quantities[$row['item_id']] ?? '0', $row['quantity'], 4);
            }
        }

        foreach ($data->dishes as $dish) {
            $item = MenuItem::query()->find($dish['menu_item_id']) ?? throw new RestaurantSetupInvalid(__('Unknown dish.'));

            if (! BigDecimal::of($dish['portions'])->isPositive()) {
                continue;
            }

            $portion = $this->book->portion($item, $dish['variant_id'] ?? null);

            if ($portion === []) {
                throw new RestaurantSetupInvalid(__('":dish" has no recipe, so its ingredients are not known. Write off the ingredients instead.', ['dish' => $item->translated('name')]));
            }

            foreach ($portion as $itemId => $quantity) {
                $quantities[$itemId] = bcadd($quantities[$itemId] ?? '0', (string) BigDecimal::of($quantity)->multipliedBy($dish['portions'])->toScale(4, RoundingMode::HalfUp), 4);
            }
        }

        if ($quantities === []) {
            throw new RestaurantSetupInvalid(__('Enter what was wasted.'));
        }

        return $this->transaction(function () use ($data, $store, $quantities): WastageEntry {
            $entry = WastageEntry::query()->create([
                'property_id' => $store->propertyId, 'wastage_no' => $this->numbers->next('wastage'), 'store_id' => $store->id, 'wasted_on' => $data->wastedOn ?? now()->toDateString(), 'reason' => $data->reason,
                'notes' => $data->notes, 'source_type' => $data->sourceType, 'source_id' => $data->sourceId, 'recorded_by' => $data->userId, 'total_cost' => '0',
            ]);
            $total = BigDecimal::zero();

            foreach ($quantities as $itemId => $quantity) {
                try {
                    $movement = $this->stock->record(new NewStockMovement(
                        MovementType::Wastage, $store->id, $itemId, $quantity, date: $entry->wasted_on->toDateString(), reason: $data->reason->value, notes: $entry->wastage_no,
                        sourceType: 'wastage_entry', sourceId: $entry->id, sourceEvent: 'wasted', idempotencyKey: 'wastage:'.$entry->id.':'.$itemId, userId: $data->userId, allowNegative: $data->allowNegative,
                    ));
                } catch (StockRuleViolated $exception) {
                    throw new RestaurantSetupInvalid($exception->getMessage(), 0, $exception);
                }

                WastageLine::query()->create([
                    'property_id' => $entry->property_id, 'wastage_entry_id' => $entry->id, 'item_id' => $itemId, 'quantity' => $quantity, 'unit_cost' => $movement->unitCost,
                    'total_cost' => BigDecimal::of($movement->totalCost)->toScale(2, RoundingMode::HalfUp), 'stock_movement_id' => $movement->id,
                ]);
                $total = $total->plus(BigDecimal::of($movement->totalCost)->toScale(2, RoundingMode::HalfUp));
            }

            $entry->forceFill(['total_cost' => (string) $total->toScale(2)])->save();
            WastageRecorded::dispatch($entry->tenant_id, $entry->property_id, $entry->id);

            return $entry->load('lines');
        }, attempts: 3);
    }
}
