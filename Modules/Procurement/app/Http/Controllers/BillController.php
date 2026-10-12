<?php

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\AuditTrail;
use Modules\Procurement\Actions\CancelVendorBill;
use Modules\Procurement\Actions\SaveVendorBill;
use Modules\Procurement\Actions\SubmitVendorBill;
use Modules\Procurement\DTOs\NewVendorBill;
use Modules\Procurement\Enums\ExpenseClass;
use Modules\Procurement\Enums\VendorBillKind;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Http\Controllers\Concerns\ProcurementScreen;
use Modules\Procurement\Http\Requests\BillRequest;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Services\ItemLabels;
use Modules\Procurement\Services\Lookups;
use Modules\Procurement\Services\OrderQuantities;

/**
 * Procurement → Vendor bills: a vendor's invoice for goods (matched to the order and the receipts) or for a service.
 */
class BillController extends Controller
{
    use ProcurementScreen;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', VendorBill::class);
        $status = VendorBillStatus::tryFrom((string) $request->query('status'));

        return view('procurement::bills.index', [
            'bills' => VendorBill::query()->with('vendor')->where('property_id', $this->propertyId())->when($status instanceof VendorBillStatus, fn ($query) => $query->where('status', $status?->value))->orderByDesc('id')->limit(100)->get(),
            'statuses' => VendorBillStatus::cases(), 'status' => $status?->value, 'canCreate' => $request->user()?->can('procurement.bill.create') ?? false, 'subtitle' => $this->propertyName(),
        ]);
    }

    public function create(Request $request, Lookups $lookups, OrderQuantities $quantities, ItemLabels $labels): View
    {
        Gate::authorize('create', VendorBill::class);
        $order = $request->filled('order') ? PurchaseOrder::query()->with(['vendor', 'lines'])->findOrFail((int) $request->query('order')) : null;

        return $this->form(null, $order, $order instanceof PurchaseOrder ? VendorBillKind::Purchase : VendorBillKind::Service, $lookups, $quantities, $labels);
    }

    public function store(BillRequest $request, SaveVendorBill $save): RedirectResponse
    {
        Gate::authorize('create', VendorBill::class);

        try {
            $bill = $save->handle(null, $this->data($request));
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.bills.show', $bill)->with('success', __('Bill :no saved as a draft.', ['no' => $bill->bill_no]));
    }

    public function show(Request $request, VendorBill $bill, ApprovalEngine $approvals, AuditTrail $audit, ItemLabels $labels, Lookups $lookups): View
    {
        Gate::authorize('view', $bill);
        $bill->load(['vendor', 'lines', 'order']);

        return view('procurement::bills.show', [
            'bill' => $bill, 'items' => $labels->for($bill->lines->pluck('item_id')->filter()), 'taxCategories' => $lookups->taxCategories(), 'department' => $lookups->departments()[$bill->department_id] ?? null,
            'approval' => $bill->approval_request_id !== null ? $approvals->find($bill->approval_request_id) : null, 'trail' => $audit->for($bill),
            'canEdit' => $bill->status === VendorBillStatus::Draft && Gate::allows('update', $bill),
            'canCancel' => in_array($bill->status, [VendorBillStatus::Draft, VendorBillStatus::Pending], true) && Gate::allows('update', $bill),
            'canPay' => in_array($bill->status, [VendorBillStatus::Approved, VendorBillStatus::Partial], true) && ($request->user()?->can('procurement.payment.create') ?? false), 'subtitle' => $this->propertyName(),
        ]);
    }

    public function edit(VendorBill $bill, Lookups $lookups, OrderQuantities $quantities, ItemLabels $labels): View
    {
        Gate::authorize('update', $bill);
        abort_unless($bill->status === VendorBillStatus::Draft, 403, __('Only a draft bill can be changed.'));
        $bill->load('lines');
        $order = $bill->purchase_order_id !== null ? PurchaseOrder::query()->with(['vendor', 'lines'])->find($bill->purchase_order_id) : null;

        return $this->form($bill, $order, $bill->kind, $lookups, $quantities, $labels);
    }

    public function update(BillRequest $request, VendorBill $bill, SaveVendorBill $save): RedirectResponse
    {
        Gate::authorize('update', $bill);

        try {
            $save->handle($bill, $this->data($request));
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.bills.show', $bill)->with('success', __('Bill saved.'));
    }

    public function submit(VendorBill $bill, SubmitVendorBill $submit): RedirectResponse
    {
        Gate::authorize('update', $bill);

        try {
            $bill = $submit->handle($bill, $this->userId());
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('procurement.bills.show', $bill)->with('success', $bill->status === VendorBillStatus::Approved ? __('Bill :no approved: it can be paid.', ['no' => $bill->bill_no]) : __('Bill :no sent for approval.', ['no' => $bill->bill_no]));
    }

    public function cancel(VendorBill $bill, CancelVendorBill $cancel): RedirectResponse
    {
        Gate::authorize('update', $bill);

        try {
            $cancel->handle($bill, $this->userId());
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('procurement.bills.show', $bill)->with('success', __('Bill cancelled.'));
    }

    private function form(?VendorBill $bill, ?PurchaseOrder $order, VendorBillKind $kind, Lookups $lookups, OrderQuantities $quantities, ItemLabels $labels): View
    {
        $rows = [];

        if ($order instanceof PurchaseOrder) {
            $items = $labels->for($order->lines->pluck('item_id'));
            $existing = $bill instanceof VendorBill ? $bill->lines->keyBy('purchase_order_line_id') : collect();

            foreach ($order->lines as $line) {
                /** @var PurchaseOrderLine $line */
                $billed = $quantities->billed($line->id, $bill?->id);
                $open = bcsub(bcsub((string) $line->received_quantity, $quantities->returned($line->id), 4), $billed, 4);
                $rows[] = ['id' => $line->id, 'item' => ($items[$line->item_id]->name ?? '#'.$line->item_id), 'unit' => $items[$line->item_id]->baseUnit ?? '', 'received' => (string) $line->received_quantity, 'billed' => $billed, 'open' => bccomp($open, '0', 4) > 0 ? $open : '0',
                    'po_price' => (string) $line->unit_price, 'quantity' => $existing->get($line->id)->quantity ?? (bccomp($open, '0', 4) > 0 ? $open : ''), 'unit_price' => $existing->get($line->id)->unit_price ?? (string) $line->unit_price];
            }
        }

        return view('procurement::bills.form', [
            'bill' => $bill, 'order' => $order, 'kind' => $kind, 'orderRows' => $rows, 'vendors' => $lookups->vendors(), 'departments' => $lookups->departments(), 'taxCategories' => $lookups->taxCategories(),
            'classes' => collect(ExpenseClass::cases())->mapWithKeys(fn (ExpenseClass $class): array => [$class->value => $class->label()])->all(),
            'serviceLines' => old('lines', $bill instanceof VendorBill && $kind === VendorBillKind::Service ? $bill->lines->map(fn ($line): array => ['description' => $line->description, 'expense_class' => $line->expense_class?->value, 'quantity' => rtrim(rtrim((string) $line->quantity, '0'), '.'), 'unit_price' => rtrim(rtrim((string) $line->unit_price, '0'), '.'), 'tax_category_id' => $line->tax_category_id ?? ''])->all()
                : [['description' => '', 'expense_class' => 'other', 'quantity' => '1', 'unit_price' => '', 'tax_category_id' => '']]),
            'canOverride' => auth()->user()?->can('procurement.bill.override-match') ?? false, 'subtitle' => $this->propertyName(),
        ]);
    }

    private function data(BillRequest $request): NewVendorBill
    {
        $kind = VendorBillKind::from((string) $request->validated('kind'));

        return new NewVendorBill(
            $this->propertyId(), (int) $request->validated('vendor_id'), $kind, $request->lines(), $this->userId(), $request->filled('purchase_order_id') ? (int) $request->validated('purchase_order_id') : null,
            $request->filled('bill_date') ? (string) $request->validated('bill_date') : null, $request->filled('due_on') ? (string) $request->validated('due_on') : null,
            $request->filled('vendor_reference') ? (string) $request->validated('vendor_reference') : null, $request->filled('department_id') ? (int) $request->validated('department_id') : null,
            $request->filled('notes') ? (string) $request->validated('notes') : null, $request->boolean('override_match') && ($request->user()?->can('procurement.bill.override-match') ?? false),
        );
    }
}
