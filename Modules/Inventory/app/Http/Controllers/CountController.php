<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Inventory\Actions\CancelStockCount;
use Modules\Inventory\Actions\CompleteStockCount;
use Modules\Inventory\Actions\SaveCountEntries;
use Modules\Inventory\Actions\StartStockCount;
use Modules\Inventory\Enums\CountStatus;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Http\Requests\CountEntriesRequest;
use Modules\Inventory\Http\Requests\CountStartRequest;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Services\CountVariance;

/**
 * Inventory → Stock counts: start a count of a store, enter what was counted, review the differences, complete it.
 */
class CountController extends Controller
{
    public function index(PropertyContext $property): View
    {
        Gate::authorize('viewAny', StockCount::class);

        return view('inventory::counts.index', [
            'counts' => StockCount::query()->with('store')->withCount('lines')->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))->orderByDesc('id')->limit(100)->get(),
            'stores' => Store::query()->where('is_active', true)->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))->orderBy('name')->pluck('name', 'id')->all(),
            'categories' => ItemCategory::query()->with('parent')->get()->mapWithKeys(fn (ItemCategory $category): array => [$category->id => $category->path()])->sort()->all(),
            'canManage' => auth()->user()?->can('inventory.count.manage') ?? false,
        ]);
    }

    public function store(CountStartRequest $request, StartStockCount $start): RedirectResponse
    {
        Gate::authorize('create', StockCount::class);

        try {
            $count = $start->handle((int) $request->validated('store_id'), $request->filled('item_category_id') ? (int) $request->validated('item_category_id') : null, $request->boolean('blind'),
                $request->filled('notes') ? (string) $request->validated('notes') : null, (int) $request->user()?->getAuthIdentifier());
        } catch (StockRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.counts.show', $count)->with('success', __('Count :no started: enter what you count.', ['no' => $count->count_no]));
    }

    public function show(StockCount $count, CountVariance $variances, AuditTrail $audit): View
    {
        Gate::authorize('view', $count);
        $count->load(['store', 'lines.item.baseUnit']);
        $rows = $count->lines->sortBy(fn ($line): string => mb_strtolower($line->item->name))->map(function ($line) use ($count, $variances): array {
            $variance = $count->status === CountStatus::Completed ? ($line->variance_quantity !== null ? (string) $line->variance_quantity : null) : $variances->variance($line, $count->store_id);

            return ['line' => $line, 'expected' => $variances->expected($line, $count->store_id), 'variance' => $variance, 'value' => $variance !== null ? (string) BigDecimal::of($variance)->multipliedBy($line->unit_cost)->toScale(2) : null];
        })->values();

        return view('inventory::counts.show', [
            'count' => $count, 'rows' => $rows, 'trail' => $audit->for($count), 'open' => $count->status === CountStatus::Counting, 'canManage' => auth()->user()?->can('inventory.count.manage') ?? false,
            'net' => (string) $rows->reduce(fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus($row['value'] ?? '0'), BigDecimal::zero()),
        ]);
    }

    public function sheet(StockCount $count): View
    {
        Gate::authorize('view', $count);
        $count->load(['store', 'lines.item.baseUnit']);

        return view('inventory::counts.sheet', ['count' => $count, 'lines' => $count->lines->sortBy(fn ($line): string => mb_strtolower($line->item->name))->values()]);
    }

    public function save(CountEntriesRequest $request, StockCount $count, SaveCountEntries $save): RedirectResponse
    {
        Gate::authorize('update', $count);

        try {
            $save->handle($count, $request->entries());
        } catch (StockRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('inventory.counts.show', $count)->with('success', __('Counts saved.'));
    }

    public function complete(StockCount $count, CompleteStockCount $complete): RedirectResponse
    {
        Gate::authorize('update', $count);

        try {
            $adjusted = $complete->handle($count, (int) auth()->id());
        } catch (StockRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('inventory.counts.show', $count)->with('success', trans_choice('Count completed: :count item adjusted.|Count completed: :count items adjusted.', $adjusted));
    }

    public function cancel(StockCount $count, CancelStockCount $cancel): RedirectResponse
    {
        Gate::authorize('update', $count);

        try {
            $cancel->handle($count);
        } catch (StockRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('inventory.counts.index')->with('success', __('Count cancelled.'));
    }
}
