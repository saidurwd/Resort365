<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Inventory\Actions\CancelTransfer;
use Modules\Inventory\Actions\DispatchTransfer;
use Modules\Inventory\Actions\ReceiveTransfer;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Http\Requests\TransferRequest;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\Store;

/**
 * Inventory → Transfers: stock sent between the stores of a property in two steps.
 */
class TransferController extends Controller
{
    public function index(PropertyContext $property): View
    {
        Gate::authorize('viewAny', StockTransfer::class);

        return view('inventory::transfers.index', [
            'transfers' => StockTransfer::query()->with(['from', 'to'])->withCount('lines')->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))->orderByDesc('id')->limit(100)->get(),
            'canManage' => auth()->user()?->can('inventory.transfer.manage') ?? false,
        ]);
    }

    public function create(PropertyContext $property): View
    {
        Gate::authorize('create', StockTransfer::class);

        return view('inventory::transfers.create', [
            'stores' => Store::query()->where('is_active', true)->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))->orderBy('name')->pluck('name', 'id')->all(),
            'items' => Item::query()->where('is_stockable', true)->with('baseUnit')->orderBy('name')->get()->mapWithKeys(fn (Item $item): array => [$item->id => $item->sku.' · '.$item->name.' ('.$item->baseUnit->code.')'])->all(),
            'lines' => old('lines', [['item_id' => '', 'quantity' => '']]),
        ]);
    }

    public function store(TransferRequest $request, DispatchTransfer $dispatch): RedirectResponse
    {
        Gate::authorize('create', StockTransfer::class);

        try {
            $transfer = $dispatch->handle((int) $request->validated('from_store_id'), (int) $request->validated('to_store_id'), $request->lines(), $request->filled('notes') ? (string) $request->validated('notes') : null, (int) $request->user()?->getAuthIdentifier());
        } catch (StockRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.transfers.show', $transfer)->with('success', __('Transfer :no dispatched.', ['no' => $transfer->transfer_no]));
    }

    public function show(StockTransfer $transfer, AuditTrail $audit): View
    {
        Gate::authorize('view', $transfer);
        $transfer->load(['from', 'to', 'lines.item.baseUnit']);

        return view('inventory::transfers.show', ['transfer' => $transfer, 'trail' => $audit->for($transfer), 'canManage' => auth()->user()?->can('inventory.transfer.manage') ?? false]);
    }

    public function receive(StockTransfer $transfer, ReceiveTransfer $receive): RedirectResponse
    {
        Gate::authorize('update', $transfer);

        try {
            $receive->handle($transfer, (int) auth()->id());
        } catch (StockRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('inventory.transfers.show', $transfer)->with('success', __('Transfer received.'));
    }

    public function cancel(StockTransfer $transfer, CancelTransfer $cancel): RedirectResponse
    {
        Gate::authorize('update', $transfer);

        try {
            $cancel->handle($transfer, (int) auth()->id());
        } catch (StockRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('inventory.transfers.show', $transfer)->with('success', __('Transfer called back: the stock is in the source store again.'));
    }
}
