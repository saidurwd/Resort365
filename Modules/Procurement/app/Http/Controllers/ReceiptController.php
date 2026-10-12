<?php

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Procurement\Actions\ReceiveGoods;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Http\Controllers\Concerns\ProcurementScreen;
use Modules\Procurement\Http\Requests\ReceiptRequest;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Services\ItemLabels;
use Modules\Procurement\Services\Lookups;

/**
 * Procurement → Goods receipts: receive what a vendor delivered against an order, accepted and rejected.
 */
class ReceiptController extends Controller
{
    use ProcurementScreen;

    public function index(): View
    {
        Gate::authorize('viewAny', GoodsReceipt::class);

        return view('procurement::receipts.index', ['receipts' => GoodsReceipt::query()->with(['order', 'vendor'])->where('property_id', $this->propertyId())->orderByDesc('id')->limit(100)->get(), 'subtitle' => $this->propertyName()]);
    }

    public function create(PurchaseOrder $order, ItemLabels $labels, Lookups $lookups): View
    {
        Gate::authorize('create', GoodsReceipt::class);
        abort_unless(in_array($order->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent, PurchaseOrderStatus::Partial], true), 403, __('Goods are received against an approved order that is not yet complete.'));
        $order->load(['vendor', 'lines']);

        return view('procurement::receipts.create', ['order' => $order, 'items' => $labels->for($order->lines->pluck('item_id')), 'store' => $lookups->stores()[$order->store_id] ?? null, 'subtitle' => $this->propertyName()]);
    }

    public function store(ReceiptRequest $request, PurchaseOrder $order, ReceiveGoods $receive): RedirectResponse
    {
        Gate::authorize('create', GoodsReceipt::class);

        try {
            $receipt = $receive->handle($order, $request->lines(), $this->userId(), $request->filled('received_on') ? (string) $request->validated('received_on') : null,
                $request->filled('delivery_note') ? (string) $request->validated('delivery_note') : null, $request->filled('notes') ? (string) $request->validated('notes') : null);
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.receipts.show', $receipt)->with('success', __('Receipt :no recorded: the stock is in.', ['no' => $receipt->receipt_no]));
    }

    public function show(GoodsReceipt $receipt, ItemLabels $labels, Lookups $lookups, AuditTrail $audit): View
    {
        Gate::authorize('view', $receipt);
        $receipt->load(['order', 'vendor', 'lines']);

        return view('procurement::receipts.show', [
            'receipt' => $receipt, 'items' => $labels->for($receipt->lines->pluck('item_id')), 'store' => $lookups->stores()[$receipt->store_id] ?? null, 'trail' => $audit->for($receipt),
            'canReturn' => auth()->user()?->can('procurement.return.create') ?? false, 'subtitle' => $this->propertyName(),
        ]);
    }
}
