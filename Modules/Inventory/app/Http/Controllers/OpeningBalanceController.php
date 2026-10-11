<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Actions\RecordOpeningBalance;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Http\Requests\OpeningBalanceRequest;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\Store;

/**
 * Inventory → Opening balances: what a store held when the system started, item by item with its cost.
 */
class OpeningBalanceController extends Controller
{
    public function create(PropertyContext $property): View
    {
        Gate::authorize('adjust', StockBalance::class);

        return view('inventory::openings.create', [
            'stores' => Store::query()->where('is_active', true)->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))->orderBy('name')->pluck('name', 'id')->all(),
            'items' => Item::query()->where('is_stockable', true)->where('is_active', true)->with('baseUnit')->orderBy('name')->get()
                ->mapWithKeys(fn (Item $item): array => [$item->id => $item->sku.' · '.$item->name.' ('.$item->baseUnit->code.')'])->all(),
            'lines' => old('lines', [['item_id' => '', 'quantity' => '', 'unit_cost' => '']]),
        ]);
    }

    public function store(OpeningBalanceRequest $request, RecordOpeningBalance $record): RedirectResponse
    {
        Gate::authorize('adjust', StockBalance::class);

        try {
            $count = $record->handle((int) $request->validated('store_id'), $request->lines(), (int) $request->user()?->getAuthIdentifier());
        } catch (StockRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.stock.index', ['store' => (int) $request->validated('store_id')])->with('success', trans_choice(':count opening balance recorded.|:count opening balances recorded.', $count));
    }
}
