<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Actions\AdjustStock;
use Modules\Inventory\Enums\AdjustmentReason;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Http\Requests\AdjustmentRequest;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\Store;

/**
 * Inventory → Adjust stock: stock found or lost, with a reason.
 */
class AdjustmentController extends Controller
{
    public function create(PropertyContext $property): View
    {
        Gate::authorize('adjust', StockBalance::class);

        return view('inventory::adjustments.create', [
            'stores' => Store::query()->where('is_active', true)->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))->orderBy('name')->pluck('name', 'id')->all(),
            'items' => Item::query()->where('is_stockable', true)->with('baseUnit')->orderBy('name')->get()->mapWithKeys(fn (Item $item): array => [$item->id => $item->sku.' · '.$item->name.' ('.$item->baseUnit->code.')'])->all(),
            'reasons' => collect(AdjustmentReason::cases())->mapWithKeys(fn (AdjustmentReason $reason): array => [$reason->value => $reason->label()])->all(),
        ]);
    }

    public function store(AdjustmentRequest $request, AdjustStock $adjust): RedirectResponse
    {
        Gate::authorize('adjust', StockBalance::class);

        try {
            $movement = $adjust->handle(
                (int) $request->validated('store_id'), (int) $request->validated('item_id'), $request->validated('direction') === 'gain', (string) $request->validated('quantity'),
                AdjustmentReason::from((string) $request->validated('reason')), $request->filled('notes') ? (string) $request->validated('notes') : null,
                $request->filled('unit_cost') ? (string) $request->validated('unit_cost') : null, (int) $request->user()?->getAuthIdentifier(),
            );
        } catch (StockRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.stock.index', ['store' => $movement->storeId])->with('success', __('Stock adjusted: :after on hand now.', ['after' => rtrim(rtrim($movement->quantityAfter, '0'), '.')]));
    }
}
