<?php

namespace Modules\Inventory\Services\Reports;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\StockValuation;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * Inventory → Reports (Step 5.8): stock valuation, consumption by department, slow-moving stock, and theoretical against
 * actual use of a store's ingredients. Each answers `title`, `columns`, `rows`, `totals`, `summary` and `note` for the
 * report table and the CSV download.
 */
class InventoryReports
{
    public function __construct(
        private readonly StockValuation $valuation,
        private readonly DepartmentDirectory $departments,
    ) {}

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function valuation(int $propertyId, ?string $asOf, ?int $storeId): array
    {
        $rows = $this->valuation->rows($propertyId, $asOf, $storeId);
        $byClass = [];
        $total = BigDecimal::zero();

        foreach ($rows as $row) {
            $byClass[$row['class']] = ($byClass[$row['class']] ?? BigDecimal::zero())->plus($row['value']);
            $total = $total->plus($row['value']);
        }

        ksort($byClass);

        return [
            'title' => __('Stock valuation'),
            'columns' => [__('Store'), __('Item'), __('Class'), __('Quantity'), __('Average cost'), __('Value')],
            'rows' => array_map(fn (array $row): array => [$row['store'], $row['name'].' ('.$row['sku'].')', $row['class'], $this->quantity($row['quantity']).' '.$row['unit'], number_format((float) $row['avg_cost'], 4), $this->money((string) $row['value'])], $rows),
            'totals' => [__('Total'), '', '', '', '', $this->money((string) $total)],
            'summary' => [['label' => __('Total value'), 'value' => $this->money((string) $total)], ...array_map(fn (string $class, BigDecimal $value): array => ['label' => ucfirst($class), 'value' => $this->money((string) $value)], array_keys($byClass), $byClass)],
            'note' => $asOf === null ? __('Today, at the weighted-average cost of each store.') : __('As at :date, from each item\'s last movement up to that day.', ['date' => CarbonImmutable::parse($asOf)->format('d M Y')]),
        ];
    }

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function departments(int $propertyId, string $from, string $to, ?int $storeId): array
    {
        $names = collect($this->departments->all(false))->mapWithKeys(fn ($department): array => [$department->id => $department->name])->all();
        $items = [];
        $sums = [];

        foreach (StockMovement::query()->where('property_id', $propertyId)->where('movement_type', MovementType::Issue->value)->whereNotNull('department_id')->whereBetween('movement_date', [$from, $to])->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))->get() as $movement) {
            $key = $movement->department_id.'|'.$movement->item_id;
            $sums[$key] ??= ['department' => $movement->department_id, 'item' => $movement->item_id, 'quantity' => BigDecimal::zero(), 'cost' => BigDecimal::zero()];
            $sums[$key]['quantity'] = $sums[$key]['quantity']->plus(BigDecimal::of($movement->quantity)->abs());
            $sums[$key]['cost'] = $sums[$key]['cost']->plus($movement->total_cost);
            $items[$movement->item_id] = true;
        }

        $known = Item::query()->with('baseUnit')->whereIn('id', array_keys($items))->get()->keyBy('id');
        uasort($sums, fn (array $a, array $b): int => [$names[$a['department']] ?? '', $known[$a['item']]->name ?? ''] <=> [$names[$b['department']] ?? '', $known[$b['item']]->name ?? '']);
        $total = BigDecimal::zero();
        $rows = [];

        foreach ($sums as $sum) {
            $item = $known[$sum['item']] ?? null;
            $rows[] = [$names[$sum['department']] ?? '#'.$sum['department'], ($item->name ?? '#'.$sum['item']), $this->quantity((string) $sum['quantity']).' '.($item->baseUnit->code ?? ''), $this->money((string) $sum['cost'])];
            $total = $total->plus($sum['cost']);
        }

        return [
            'title' => __('Consumption by department'), 'columns' => [__('Department'), __('Item'), __('Quantity'), __('Cost')], 'rows' => $rows, 'totals' => [__('Total'), '', '', $this->money((string) $total)],
            'summary' => [['label' => __('Issued'), 'value' => $this->money((string) $total)]], 'note' => __('Stock issued to departments (requisitions, direct and amenity issues) between :from and :to, at the cost it left stock.', ['from' => CarbonImmutable::parse($from)->format('d M Y'), 'to' => CarbonImmutable::parse($to)->format('d M Y')]),
        ];
    }

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function slowMoving(int $propertyId, int $days, ?int $storeId, string $today): array
    {
        $cut = CarbonImmutable::parse($today)->subDays($days)->toDateString();
        $balances = StockBalance::query()->with(['store', 'item.baseUnit'])->where('property_id', $propertyId)->where('quantity', '>', 0)->when($storeId !== null, fn ($query) => $query->where('store_id', $storeId))->get();
        $lastOut = StockMovement::query()->where('property_id', $propertyId)->where('quantity', '<', 0)->selectRaw('store_id, item_id, max(movement_date) as last_date')->groupBy('store_id', 'item_id')->get()
            ->mapWithKeys(fn ($row): array => [$row->store_id.'|'.$row->item_id => (string) $row->getAttribute('last_date')])->all();
        $rows = [];
        $total = BigDecimal::zero();

        foreach ($balances as $balance) {
            $last = $lastOut[$balance->store_id.'|'.$balance->item_id] ?? null;

            if ($last !== null && substr($last, 0, 10) >= $cut) {
                continue;
            }

            $value = BigDecimal::of($balance->quantity)->multipliedBy($balance->avg_cost)->toScale(2, RoundingMode::HalfUp);
            $total = $total->plus($value);
            $rows[] = ['sort' => (string) $value, 'cells' => [$balance->store->name, $balance->item->name.' ('.$balance->item->sku.')', $this->quantity((string) $balance->quantity).' '.$balance->item->baseUnit->code, $this->money((string) $value), $last !== null ? CarbonImmutable::parse(substr($last, 0, 10))->format('d M Y') : __('never'),
                $last !== null ? (string) CarbonImmutable::parse(substr($last, 0, 10))->diffInDays(CarbonImmutable::parse($today)) : '—']];
        }

        usort($rows, fn (array $a, array $b): int => (float) $b['sort'] <=> (float) $a['sort']);

        return [
            'title' => __('Slow-moving stock'), 'columns' => [__('Store'), __('Item'), __('On hand'), __('Value'), __('Last used'), __('Days idle')], 'rows' => array_column($rows, 'cells'), 'totals' => [__('Total'), '', '', $this->money((string) $total), '', ''],
            'summary' => [['label' => __('Tied up'), 'value' => $this->money((string) $total)], ['label' => __('Items'), 'value' => (string) count($rows)]], 'note' => __('Items held with nothing taken out for :days days or more, largest value first.', ['days' => $days]),
        ];
    }

    /**
     * Per ingredient of one store between two dates: what the sales' recipes should have used against what actually left the
     * shelf (the count adjustments are the difference nobody explained).
     *
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function theoreticalVsActual(int $propertyId, string $from, string $to, ?int $storeId): array
    {
        if ($storeId === null) {
            return ['title' => __('Theoretical vs actual use'), 'columns' => [], 'rows' => [], 'totals' => null, 'summary' => [], 'note' => __('Choose a store.')];
        }

        $movements = StockMovement::query()->where('property_id', $propertyId)->where('store_id', $storeId)->where('movement_date', '<=', $to)->orderBy('id')->get();
        $items = Item::query()->with('baseUnit')->whereIn('id', $movements->pluck('item_id')->unique())->get()->keyBy('id');
        $figures = [];

        foreach ($movements as $movement) {
            $figure = $figures[$movement->item_id] ?? ['opening' => BigDecimal::zero(), 'received' => BigDecimal::zero(), 'sales' => BigDecimal::zero(), 'wastage' => BigDecimal::zero(), 'other' => BigDecimal::zero(), 'count' => BigDecimal::zero(), 'count_value' => BigDecimal::zero(), 'closing' => BigDecimal::zero()];
            $quantity = BigDecimal::of($movement->quantity);
            $figure['closing'] = BigDecimal::of($movement->quantity_after);

            if ($movement->movement_date->toDateString() < $from) {
                $figure['opening'] = BigDecimal::of($movement->quantity_after);
            } elseif ($movement->source_type === 'stock_count') {
                $figure['count'] = $figure['count']->plus($quantity);
                $figure['count_value'] = $figure['count_value']->plus($quantity->multipliedBy($movement->unit_cost)->toScale(2, RoundingMode::HalfUp));
            } elseif ($movement->movement_type === MovementType::Consumption || ($movement->movement_type === MovementType::Reversal && $movement->source_event === 'consumption_reversed')) {
                $figure['sales'] = $figure['sales']->minus($quantity);
            } elseif ($movement->movement_type === MovementType::Wastage) {
                $figure['wastage'] = $figure['wastage']->minus($quantity);
            } elseif ($quantity->isPositive()) {
                $figure['received'] = $figure['received']->plus($quantity);
            } else {
                $figure['other'] = $figure['other']->minus($quantity);
            }

            $figures[$movement->item_id] = $figure;
        }

        $rows = [];
        $value = BigDecimal::zero();

        foreach ($figures as $itemId => $figure) {
            $item = $items[$itemId] ?? null;

            if ($item === null || ($figure['opening']->isZero() && $figure['received']->isZero() && $figure['sales']->isZero() && $figure['wastage']->isZero() && $figure['other']->isZero() && $figure['count']->isZero())) {
                continue;
            }

            $percent = $figure['sales']->isPositive() ? $figure['count']->multipliedBy('100')->dividedBy($figure['sales'], 1, RoundingMode::HalfUp).'%' : '—';
            $value = $value->plus($figure['count_value']);
            $rows[] = ['sort' => $item->name, 'cells' => [
                $item->name.' ('.$item->sku.')', $item->baseUnit->code, $this->quantity((string) $figure['opening']), $this->quantity((string) $figure['received']), $this->quantity((string) $figure['sales']), $this->quantity((string) $figure['wastage']), $this->quantity((string) $figure['other']),
                $this->quantity((string) $figure['count']), $this->quantity((string) $figure['closing']), $this->money((string) $figure['count_value']), $percent,
            ]];
        }

        usort($rows, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return [
            'title' => __('Theoretical vs actual use'),
            'columns' => [__('Ingredient'), __('Unit'), __('Opening'), __('Received'), __('Used in sales (theoretical)'), __('Wastage'), __('Issued, transferred, returned'), __('Count variance'), __('Closing'), __('Variance value'), __('Variance % of use')],
            'rows' => array_column($rows, 'cells'), 'totals' => [__('Total'), '', '', '', '', '', '', '', '', $this->money((string) $value), ''],
            'summary' => [['label' => __('Count variance value'), 'value' => $this->money((string) $value)]],
            'note' => __('Opening + received − sales use − wastage − other outflows + count variance = closing. A negative count variance is stock the count could not find: over-portioning, waste nobody recorded, or theft.'),
        ];
    }

    private function money(string $amount): string
    {
        return number_format((float) $amount, 2);
    }

    private function quantity(string $amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0';
    }
}
