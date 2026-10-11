<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Modules\Inventory\Contracts\StockFacts;
use Modules\Inventory\DTOs\StockAdjustmentFact;
use Modules\Inventory\DTOs\StockIssueFact;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\StockCountLine;
use Modules\Inventory\Models\StockIssue;
use Modules\Inventory\Models\StockIssueLine;
use Modules\Inventory\Models\StockMovement;

class StockFactsService implements StockFacts
{
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

    public function history(): array
    {
        $adjustments = StockMovement::query()->whereIn('movement_type', [MovementType::AdjustmentIn->value, MovementType::AdjustmentOut->value])
            ->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', '!=', 'stock_count'))->orderBy('id')->pluck('id')
            ->map(fn ($id): array => ['type' => 'stock_movement', 'id' => (int) $id])->all();

        foreach (StockCount::query()->where('status', 'completed')->orderBy('id')->pluck('id') as $id) {
            $adjustments[] = ['type' => 'stock_count', 'id' => (int) $id];
        }

        return ['issues' => StockIssue::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(), 'adjustments' => $adjustments];
    }
}
