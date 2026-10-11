<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\ItemStoreLevel;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\Store;

/**
 * Inventory → Stock on hand: what each store holds, at what average cost and value, with the items at or below their
 * reorder level flagged.
 */
class StockController extends Controller
{
    public function index(Request $request, PropertyContext $property): View
    {
        Gate::authorize('viewAny', StockBalance::class);
        $stores = Store::query()->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))->orderBy('name')->get();
        $store = $stores->firstWhere('id', (int) $request->query('store')) ?? $stores->first();
        $rows = collect();
        $total = '0.00';

        if ($store instanceof Store) {
            $levels = ItemStoreLevel::query()->where('store_id', $store->id)->pluck('reorder_level', 'item_id');
            $rows = StockBalance::query()->with(['item.category', 'item.baseUnit'])->where('store_id', $store->id)
                ->when($request->filled('category'), fn ($query) => $query->whereHas('item', fn ($item) => $item->whereIn('item_category_id', $this->withDescendants((int) $request->query('category')))))
                ->get()->filter(fn (StockBalance $row): bool => ! BigDecimal::of($row->quantity)->isZero() || $request->boolean('zero'))
                ->map(function (StockBalance $row) use ($levels): array {
                    $level = $levels[$row->item_id] ?? $row->item->reorder_level;

                    return ['balance' => $row, 'level' => (string) $level, 'low' => BigDecimal::of($level)->isPositive() && BigDecimal::of($row->quantity)->isLessThanOrEqualTo($level)];
                })
                ->when($request->boolean('low'), fn ($rows) => $rows->filter(fn (array $row): bool => $row['low']))
                ->sortBy(fn (array $row): string => mb_strtolower($row['balance']->item->name))->values();
            $total = (string) $rows->reduce(fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus($row['balance']->value()), BigDecimal::zero());
        }

        return view('inventory::stock.index', [
            'stores' => $stores->mapWithKeys(fn (Store $row): array => [$row->id => $row->name])->all(), 'store' => $store, 'rows' => $rows, 'total' => $total,
            'categories' => ItemCategory::query()->with('parent')->get()->mapWithKeys(fn (ItemCategory $category): array => [$category->id => $category->path()])->sort()->all(),
            'canAdjust' => $request->user()?->can('inventory.stock.adjust') ?? false,
        ]);
    }

    /**
     * @return list<int>
     */
    private function withDescendants(int $categoryId): array
    {
        $ids = [$categoryId];
        $pending = [$categoryId];

        while ($pending !== []) {
            $children = ItemCategory::query()->whereIn('parent_id', $pending)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $pending = array_values(array_diff($children, $ids));
            $ids = [...$ids, ...$pending];
        }

        return $ids;
    }
}
