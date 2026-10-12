<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Settings;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\DTOs\StockBalanceSummary;
use Modules\Inventory\DTOs\StockMovementSummary;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Events\StockBelowReorderLevel;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemStoreLevel;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Store;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * The stock ledger writer (ARCHITECTURE §5.12, Step 5.3). A movement locks the balance row of its item and store,
 * works out the new quantity and weighted-average cost, appends the ledger line and updates the balance in one
 * transaction, so two movements at once can never read the same balance. Stock cannot go negative unless the tenant
 * allows it (`inventory.allow_negative_stock`).
 */
class StockMovementService implements StockMovementContract
{
    public function __construct(
        private readonly ItemCatalog $catalog,
        private readonly StockCostCalculator $costs,
        private readonly Settings $settings,
        private readonly PropertyDirectory $properties,
    ) {}

    public function record(NewStockMovement $movement): StockMovementSummary
    {
        if ($movement->idempotencyKey !== null) {
            $existing = StockMovement::query()->where('idempotency_key', $movement->idempotencyKey)->first();

            if ($existing instanceof StockMovement) {
                return $this->summary($existing);
            }
        }

        $store = Store::query()->find($movement->storeId) ?? throw new StockRuleViolated(__('Unknown store.'));
        $item = Item::query()->find($movement->itemId) ?? throw new StockRuleViolated(__('Unknown item.'));

        if (! $item->is_stockable) {
            throw new StockRuleViolated(__(':item is not a stocked item.', ['item' => $item->name]));
        }

        $quantity = BigDecimal::of($movement->quantity);

        if (! $quantity->isPositive()) {
            throw new StockRuleViolated(__('The quantity must be more than zero.'));
        }

        $adds = $movement->type->adds() ?? $movement->adds ?? throw new StockRuleViolated(__('A reversal says which way it goes.'));
        $base = $movement->unitId !== null ? $this->catalog->toBaseQuantity($item->id, $movement->quantity, $movement->unitId) : (string) $quantity;
        $unitCost = $this->costPerBaseUnit($movement, $base, $item);
        $date = $movement->date ?? $this->properties->find($store->property_id)->businessDate ?? now()->toDateString();

        try {
            return DB::transaction(function () use ($movement, $store, $item, $adds, $base, $unitCost, $date): StockMovementSummary {
                // Lock the balance row first; only when there is none create it (a plain insert-ignore before the lock would
                // take a shared lock on an existing row and deadlock with the next mover waiting to upgrade it).
                $balance = StockBalance::query()->where('store_id', $store->id)->where('item_id', $item->id)->lockForUpdate()->first();

                if (! $balance instanceof StockBalance) {
                    StockBalance::query()->insertOrIgnore([[
                        'tenant_id' => $store->tenant_id, 'property_id' => $store->property_id, 'store_id' => $store->id, 'item_id' => $item->id,
                        'quantity' => '0', 'avg_cost' => '0', 'created_at' => now(), 'updated_at' => now(),
                    ]]);
                    $balance = StockBalance::query()->where('store_id', $store->id)->where('item_id', $item->id)->lockForUpdate()->firstOrFail();
                }

                $before = (string) $balance->quantity;
                $cost = $unitCost ?? ($adds && ! BigDecimal::of($balance->avg_cost)->isPositive() && $item->standard_cost !== null ? (string) $item->standard_cost : null);
                $result = $this->costs->apply($before, (string) $balance->avg_cost, $adds ? $base : '-'.$base, $cost);

                if (BigDecimal::of($result['quantity'])->isNegative() && ! $movement->allowNegative && ! $this->allowNegative()) {
                    throw new InsufficientStock(__(':store holds only :held :unit of :item, not :asked.', [
                        'store' => $store->name, 'held' => $this->plain($before), 'unit' => $item->baseUnit->code, 'item' => $item->name, 'asked' => $this->plain($base),
                    ]));
                }

                $line = StockMovement::query()->create([
                    'property_id' => $store->property_id, 'store_id' => $store->id, 'department_id' => $movement->departmentId, 'item_id' => $item->id, 'movement_type' => $movement->type, 'movement_date' => $date,
                    'quantity' => $adds ? $base : '-'.$base, 'unit_cost' => $result['unit_cost'], 'total_cost' => $this->costs->value($base, $result['unit_cost']), 'quantity_after' => $result['quantity'],
                    'avg_cost_after' => $result['avg_cost'], 'reason' => $movement->reason, 'notes' => $movement->notes !== null ? mb_substr($movement->notes, 0, 300) : null,
                    'source_type' => $movement->sourceType, 'source_id' => $movement->sourceId, 'source_event' => $movement->sourceEvent, 'idempotency_key' => $movement->idempotencyKey,
                    'reverses_id' => $movement->reverses, 'created_by' => $movement->userId,
                ]);
                $balance->forceFill(['quantity' => $result['quantity'], 'avg_cost' => $result['avg_cost'], 'last_movement_id' => $line->id])->save();

                if (! $adds) {
                    $this->warnIfLow($store, $item, $before, $result['quantity']);
                }

                return $this->summary($line);
            }, 5);
        } catch (QueryException $exception) {
            if ($movement->idempotencyKey !== null) {
                $existing = StockMovement::query()->where('idempotency_key', $movement->idempotencyKey)->first();

                if ($existing instanceof StockMovement) {
                    return $this->summary($existing);
                }
            }

            throw $exception;
        }
    }

    public function balance(int $storeId, int $itemId): StockBalanceSummary
    {
        $balance = StockBalance::query()->where('store_id', $storeId)->where('item_id', $itemId)->first();

        return $balance instanceof StockBalance
            ? new StockBalanceSummary($storeId, $itemId, (string) $balance->quantity, (string) $balance->avg_cost, $balance->value())
            : new StockBalanceSummary($storeId, $itemId, '0.0000', '0.0000', '0.00');
    }

    /**
     * The cost per base unit: what the caller gave per unit of the unit it used, converted.
     */
    private function costPerBaseUnit(NewStockMovement $movement, string $base, Item $item): ?string
    {
        if ($movement->unitCost === null) {
            if ($movement->type->adds() === true && in_array($movement->type, [MovementType::Receipt, MovementType::Opening, MovementType::TransferIn, MovementType::ProductionIn], true)) {
                throw new StockRuleViolated(__('A :type needs the cost of :item.', ['type' => mb_strtolower($movement->type->label()), 'item' => $item->name]));
            }

            return null;
        }

        if ($movement->unitId === null || $movement->unitId === $item->base_unit_id) {
            return $movement->unitCost;
        }

        return (string) BigDecimal::of($movement->unitCost)->multipliedBy($movement->quantity)->dividedBy($base, 4, RoundingMode::HalfUp);
    }

    private function warnIfLow(Store $store, Item $item, string $before, string $after): void
    {
        $level = ItemStoreLevel::query()->where('item_id', $item->id)->where('store_id', $store->id)->value('reorder_level') ?? $item->reorder_level;

        if (BigDecimal::of($level)->isPositive() && BigDecimal::of($before)->isGreaterThan($level) && BigDecimal::of($after)->isLessThanOrEqualTo($level)) {
            StockBelowReorderLevel::dispatch($store->tenant_id, $store->property_id, $store->id, $item->id, $after, (string) $level);
        }
    }

    private function allowNegative(): bool
    {
        return (bool) $this->settings->get('inventory.allow_negative_stock');
    }

    private function plain(string $number): string
    {
        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }

    private function summary(StockMovement $line): StockMovementSummary
    {
        return new StockMovementSummary(
            $line->id, $line->movement_type, $line->store_id, $line->item_id, (string) $line->quantity, (string) $line->unit_cost, (string) $line->total_cost,
            (string) $line->quantity_after, (string) $line->avg_cost_after, $line->movement_date->toDateString(),
        );
    }
}
