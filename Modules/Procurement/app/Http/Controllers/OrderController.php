<?php

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\AuditTrail;
use Modules\Procurement\Actions\CancelPurchaseOrder;
use Modules\Procurement\Actions\CreateOrderFromRequisition;
use Modules\Procurement\Actions\SavePurchaseOrder;
use Modules\Procurement\Actions\SendPurchaseOrder;
use Modules\Procurement\Actions\SubmitPurchaseOrder;
use Modules\Procurement\DTOs\NewPurchaseOrder;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Http\Controllers\Concerns\ProcurementScreen;
use Modules\Procurement\Http\Requests\OrderRequest;
use Modules\Procurement\Http\Requests\RequisitionOrderRequest;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Services\ItemLabels;
use Modules\Procurement\Services\Lookups;
use Modules\Procurement\Services\ProcurementDocuments;

/**
 * Procurement → Purchase orders: write an order (or take one from a requisition or an RFQ), send it for approval, email
 * it to the vendor as a PDF.
 */
class OrderController extends Controller
{
    use ProcurementScreen;

    public function index(Request $request, Lookups $lookups): View
    {
        Gate::authorize('viewAny', PurchaseOrder::class);
        $status = PurchaseOrderStatus::tryFrom((string) $request->query('status'));

        return view('procurement::orders.index', [
            'orders' => PurchaseOrder::query()->with('vendor')->where('property_id', $this->propertyId())->when($status instanceof PurchaseOrderStatus, fn ($query) => $query->where('status', $status?->value))->orderByDesc('id')->limit(100)->get(),
            'statuses' => PurchaseOrderStatus::cases(), 'status' => $status?->value, 'canCreate' => $request->user()?->can('procurement.order.create') ?? false, 'subtitle' => $this->propertyName(),
        ]);
    }

    public function create(Lookups $lookups): View
    {
        Gate::authorize('create', PurchaseOrder::class);

        return $this->form(null, $lookups);
    }

    public function store(OrderRequest $request, SavePurchaseOrder $save): RedirectResponse
    {
        Gate::authorize('create', PurchaseOrder::class);

        try {
            $order = $save->handle(null, $this->data($request));
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.orders.show', $order)->with('success', __('Order :no saved as a draft.', ['no' => $order->order_no]));
    }

    public function fromRequisition(RequisitionOrderRequest $request, CreateOrderFromRequisition $create): RedirectResponse
    {
        Gate::authorize('create', PurchaseOrder::class);
        $requisition = PurchaseRequisition::query()->with('lines')->findOrFail((int) $request->validated('purchase_requisition_id'));

        try {
            $order = $create->handle($requisition, (int) $request->validated('vendor_id'), (int) $request->validated('store_id'), $this->userId(), $request->filled('expected_on') ? (string) $request->validated('expected_on') : null);
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.orders.show', $order)->with('success', __('Order :no drafted from the requisition: check the prices and send it for approval.', ['no' => $order->order_no]));
    }

    public function show(Request $request, PurchaseOrder $order, ApprovalEngine $approvals, AuditTrail $audit, ItemLabels $labels, Lookups $lookups): View
    {
        Gate::authorize('view', $order);
        $order->load(['vendor', 'lines']);

        return view('procurement::orders.show', [
            'order' => $order, 'items' => $labels->for($order->lines->pluck('item_id')), 'store' => $lookups->stores()[$order->store_id] ?? null, 'department' => $lookups->departments()[$order->department_id] ?? null,
            'taxCategories' => $lookups->taxCategories(), 'approval' => $order->approval_request_id !== null ? $approvals->find($order->approval_request_id) : null, 'trail' => $audit->for($order),
            'canEdit' => $order->status === PurchaseOrderStatus::Draft && Gate::allows('update', $order),
            'canSend' => in_array($order->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent], true) && Gate::allows('send', $order),
            'canCancel' => in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Pending, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent], true) && Gate::allows('update', $order),
            'subtitle' => $this->propertyName(),
        ]);
    }

    public function edit(PurchaseOrder $order, Lookups $lookups): View
    {
        Gate::authorize('update', $order);
        abort_unless($order->status === PurchaseOrderStatus::Draft, 403, __('Only a draft order can be changed.'));

        return $this->form($order->load('lines'), $lookups);
    }

    public function update(OrderRequest $request, PurchaseOrder $order, SavePurchaseOrder $save): RedirectResponse
    {
        Gate::authorize('update', $order);

        try {
            $save->handle($order, $this->data($request));
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.orders.show', $order)->with('success', __('Order saved.'));
    }

    public function submit(PurchaseOrder $order, SubmitPurchaseOrder $submit): RedirectResponse
    {
        Gate::authorize('update', $order);

        try {
            $order = $submit->handle($order, $this->userId());
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('procurement.orders.show', $order)->with('success', $order->status === PurchaseOrderStatus::Approved ? __('Order :no approved: it can be sent to the vendor.', ['no' => $order->order_no]) : __('Order :no sent for approval.', ['no' => $order->order_no]));
    }

    public function send(Request $request, PurchaseOrder $order, SendPurchaseOrder $send): RedirectResponse
    {
        Gate::authorize('send', $order);
        $request->validate(['to' => ['nullable', 'email', 'max:150']]);

        try {
            $order = $send->handle($order, $request->filled('to') ? (string) $request->input('to') : null);
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('procurement.orders.show', $order)->with('success', __('Order :no emailed to :address.', ['no' => $order->order_no, 'address' => $order->sent_to]));
    }

    public function cancel(PurchaseOrder $order, CancelPurchaseOrder $cancel): RedirectResponse
    {
        Gate::authorize('update', $order);

        try {
            $cancel->handle($order, $this->userId());
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('procurement.orders.show', $order)->with('success', __('Order cancelled.'));
    }

    public function pdf(PurchaseOrder $order, ProcurementDocuments $documents): Response
    {
        Gate::authorize('view', $order);

        return response($documents->purchaseOrder($order), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$order->order_no.'.pdf"']);
    }

    private function form(?PurchaseOrder $order, Lookups $lookups): View
    {
        return view('procurement::orders.form', [
            'order' => $order, 'vendors' => $lookups->vendors(), 'stores' => $lookups->stores(), 'departments' => $lookups->departments(), 'items' => $lookups->items(), 'taxCategories' => $lookups->taxCategories(),
            'lines' => old('lines', $order instanceof PurchaseOrder ? $order->lines->map(fn ($line): array => ['item_id' => $line->item_id, 'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.'), 'unit_price' => rtrim(rtrim((string) $line->unit_price, '0'), '.'), 'tax_category_id' => $line->tax_category_id ?? ''])->all()
                : [['item_id' => '', 'quantity' => '', 'unit_price' => '', 'tax_category_id' => '']]), 'subtitle' => $this->propertyName(),
        ]);
    }

    private function data(OrderRequest $request): NewPurchaseOrder
    {
        return new NewPurchaseOrder(
            $this->propertyId(), (int) $request->validated('vendor_id'), (int) $request->validated('store_id'), $request->lines(), $this->userId(),
            $request->filled('department_id') ? (int) $request->validated('department_id') : null, $request->filled('ordered_on') ? (string) $request->validated('ordered_on') : null,
            $request->filled('expected_on') ? (string) $request->validated('expected_on') : null, $request->filled('notes') ? (string) $request->validated('notes') : null,
            paymentTermsDays: $request->filled('payment_terms_days') ? (int) $request->validated('payment_terms_days') : null,
        );
    }
}
