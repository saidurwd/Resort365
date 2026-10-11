<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Http\Requests\ItemRequest;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Services\ItemsTable;

/**
 * Inventory → Items: the catalogue, and the form for an item with its own unit conversions and its levels per store.
 */
class ItemController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Item::class);

        return view('inventory::items.index', [
            'columns' => ItemsTable::columns(), 'categories' => $this->categoryOptions(), 'canManage' => $request->user()?->can('inventory.item.manage') ?? false,
            'canImport' => $request->user()?->can('inventory.item.import') ?? false,
        ]);
    }

    public function data(Request $request, ItemsTable $table): JsonResponse
    {
        Gate::authorize('viewAny', Item::class);

        return $table->toJson($request->filled('category') ? (int) $request->query('category') : null, $request->query('active') !== null ? (string) $request->query('active') : null,
            $request->query('stockable') !== null ? (string) $request->query('stockable') : null, $request->user()?->can('inventory.item.manage') ?? false);
    }

    public function create(): View
    {
        Gate::authorize('create', Item::class);

        return $this->form(null);
    }

    public function store(ItemRequest $request, SaveItem $save): RedirectResponse
    {
        Gate::authorize('create', Item::class);

        try {
            $item = $save->handle(null, $request->item(), $request->conversions(), $request->levels());
        } catch (InventoryRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.items.edit', $item)->with('success', __('Item :sku added.', ['sku' => $item->sku]));
    }

    public function edit(Item $item): View
    {
        Gate::authorize('update', $item);

        return $this->form($item);
    }

    public function update(ItemRequest $request, Item $item, SaveItem $save): RedirectResponse
    {
        Gate::authorize('update', $item);

        try {
            $save->handle($item, $request->item(), $request->conversions(), $request->levels());
        } catch (InventoryRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.items.edit', $item)->with('success', __('Item saved.'));
    }

    private function form(?Item $item): View
    {
        $item?->load(['conversions', 'levels']);
        $units = Unit::query()->where('is_active', true)->orderBy('kind')->orderBy('code')->get();
        $propertyId = app(PropertyContext::class)->currentId();

        return view('inventory::items.form', [
            'item' => $item, 'categories' => $this->categoryOptions(), 'units' => $units->mapWithKeys(fn (Unit $unit): array => [$unit->id => $unit->code.' · '.$unit->name])->all(),
            'stores' => Store::query()->where('is_active', true)->when($propertyId !== null, fn ($query) => $query->where('property_id', $propertyId))->orderBy('name')->pluck('name', 'id')->all(),
            'conversions' => old('conversions', $item?->conversions->map(fn ($row): array => ['unit_id' => $row->unit_id, 'factor' => rtrim(rtrim((string) $row->factor, '0'), '.')])->all() ?? []),
            'levels' => old('levels', $item?->levels->map(fn ($row): array => ['store_id' => $row->store_id, 'par_level' => $row->par_level !== null ? rtrim(rtrim((string) $row->par_level, '0'), '.') : '', 'reorder_level' => $row->reorder_level !== null ? rtrim(rtrim((string) $row->reorder_level, '0'), '.') : ''])->all() ?? []),
            'trail' => $item instanceof Item ? app(AuditTrail::class)->for($item) : [],
            'balances' => $item instanceof Item ? StockBalance::query()->with('store')->where('item_id', $item->id)->get()->filter(fn (StockBalance $row): bool => (float) $row->quantity !== 0.0)->values() : collect(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function categoryOptions(): array
    {
        return ItemCategory::query()->with('parent')->where('is_active', true)->get()->mapWithKeys(fn (ItemCategory $category): array => [$category->id => $category->path()])->sort()->all();
    }
}
