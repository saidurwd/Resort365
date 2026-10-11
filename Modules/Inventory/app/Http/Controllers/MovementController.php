<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Inventory\Actions\ReverseMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Services\MovementsTable;

/**
 * Inventory → Movements: the stock ledger, filtered; a movement can be reversed by those who may adjust stock.
 */
class MovementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StockBalance::class);

        return view('inventory::movements.index', [
            'columns' => MovementsTable::columns(), 'stores' => Store::query()->orderBy('name')->pluck('name', 'id')->all(),
            'items' => Item::query()->orderBy('name')->get()->mapWithKeys(fn (Item $item): array => [$item->id => $item->sku.' · '.$item->name])->all(),
            'types' => collect(MovementType::cases())->mapWithKeys(fn (MovementType $type): array => [$type->value => $type->label()])->all(),
            'canReverse' => $request->user()?->can('inventory.stock.adjust') ?? false,
        ]);
    }

    public function data(Request $request, MovementsTable $table): JsonResponse
    {
        Gate::authorize('viewAny', StockBalance::class);

        return $table->toJson([
            'store' => $request->filled('store') ? (int) $request->query('store') : null, 'item' => $request->filled('item') ? (int) $request->query('item') : null,
            'type' => $request->query('type') !== null ? (string) $request->query('type') : null, 'from' => $request->filled('from') ? (string) $request->query('from') : null,
            'to' => $request->filled('to') ? (string) $request->query('to') : null,
        ]);
    }

    public function reverse(Request $request, StockMovement $movement, ReverseMovement $reverse): RedirectResponse
    {
        Gate::authorize('adjust', StockBalance::class);
        $request->validate(['reason' => ['required', 'string', 'max:200']]);

        try {
            $reverse->handle($movement, (string) $request->input('reason'), (int) $request->user()?->getAuthIdentifier());
        } catch (StockRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('inventory.movements.index')->with('success', __('Movement #:id reversed.', ['id' => $movement->id]));
    }
}
