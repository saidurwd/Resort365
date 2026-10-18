<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Modules\Inventory\Contracts\StockFacts;
use Modules\Inventory\DTOs\StockAdjustmentFact;
use Modules\Inventory\DTOs\StockIssueFact;
use Modules\Inventory\DTOs\StockOpeningFact;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\StockCountLine;
use Modules\Inventory\Models\StockIssue;
use Modules\Inventory\Models\StockIssueLine;
use Modules\Inventory\Models\StockMovement;

class StockFactsService implements StockFacts
{
    public function __construct(private readonly StockValuation $valuation) {}

    public function issue(int $issueId): ?StockIssueFact
    {
        $issue = StockIssue::query()->with('lines.item.category.parent')->find($issueId);

        if (! $issue instanceof StockIssue) {
            return null;
        }

        $byClass = [];

        foreach ($issue->lines as $line) {
            /** @var StockIssueLine $line */
            $class = $line->item->category->stockClass()->value;
            $byClass[$class] = (string) BigDecimal::of($byClass[$class] ?? '0')->plus($line->total_cost)->toScale(2);
        }

        return new StockIssueFact($issue->id, $issue->issue_no, $issue->property_id, $issue->department_id, $issue->issued_on->toDateString(), $byClass);
    }

    public function adjustment(string $sourceType, int $sourceId): ?StockAdjustmentFact
    {
        $movements = match ($sourceType) {
            'stock_movement' => StockMovement::query()->with('item.category.parent')->whereKey($sourceId)->whereIn('movement_type', [MovementType::AdjustmentIn->value, MovementType::AdjustmentOut->value])->get(),
            'stock_count' => StockMovement::query()->with('item.category.parent')->whereIn('id', StockCountLine::query()->where('stock_count_id', $sourceId)->whereNotNull('adjustment_movement_id')->pluck('adjustment_movement_id'))->get(),
            default => collect(),
        };

        if ($movements->isEmpty()) {
            return null;
        }

        $byClass = [];

        foreach ($movements as $movement) {
            /** @var StockMovement $movement */
            $class = $movement->item->category->stockClass()->value;
            $value = (float) $movement->quantity < 0 ? BigDecimal::of($movement->total_cost)->negated() : BigDecimal::of($movement->total_cost);
            $byClass[$class] = (string) BigDecimal::of($byClass[$class] ?? '0')->plus($value)->toScale(2);
        }

        $first = $movements->first();
        $reference = $sourceType === 'stock_count' ? (StockCount::query()->whereKey($sourceId)->value('count_no') ?? 'count #'.$sourceId) : 'adjustment #'.$sourceId;

        return new StockAdjustmentFact($sourceType, $sourceId, $first->property_id, (string) $reference, $first->movement_date->toDateString(), $byClass);
    }

    public function opening(int $batchId): ?StockOpeningFact
    {
        $first = StockMovement::query()->with('item.category.parent')->where('movement_type', MovementType::Opening->value)->find($batchId);

        if (! $first instanceof StockMovement) {
            return null;
        }

        $byClass = [];
        $next = $batchId;

        foreach (StockMovement::query()->with('item.category.parent')->where('movement_type', MovementType::Opening->value)->where('store_id', $first->store_id)->where('movement_date', $first->movement_date->toDateString())->where('id', '>=', $batchId)->orderBy('id')->get() as $movement) {
            if ($movement->id !== $next) {
                break;
            }

            $class = $movement->item->category->stockClass()->value;
            $byClass[$class] = (string) BigDecimal::of($byClass[$class] ?? '0')->plus($movement->total_cost)->toScale(2);
            $next++;
        }

        return new StockOpeningFact($batchId, $first->property_id, $first->movement_date->toDateString(), $byClass);
    }

    public function valuation(int $propertyId, ?string $asOf = null): array
    {
        return ['stock' => $this->valuation->byClass($propertyId, $asOf), 'in_transit' => $this->valuation->inTransit($propertyId)];
    }

    public function history(): array
    {
        $adjustments = StockMovement::query()->whereIn('movement_type', [MovementType::AdjustmentIn->value, MovementType::AdjustmentOut->value])
            ->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', '!=', 'stock_count'))->orderBy('id')->pluck('id')
            ->map(fn ($id): array => ['type' => 'stock_movement', 'id' => (int) $id])->all();

        foreach (StockCount::query()->where('status', 'completed')->orderBy('id')->pluck('id') as $id) {
            $adjustments[] = ['type' => 'stock_count', 'id' => (int) $id];
        }

        $openings = [];
        $previous = null;

        foreach (StockMovement::query()->where('movement_type', MovementType::Opening->value)->orderBy('id')->get(['id', 'store_id', 'movement_date']) as $movement) {
            $key = $movement->store_id.'|'.$movement->movement_date->toDateString();
            $consecutive = $previous !== null && $previous['key'] === $key && $movement->id === $previous['id'] + 1;

            if (! $consecutive) {
                $openings[] = $movement->id;
            }

            $previous = ['key' => $key, 'id' => $movement->id];
        }

        return ['issues' => StockIssue::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(), 'adjustments' => $adjustments, 'openings' => $openings];
    }
}
