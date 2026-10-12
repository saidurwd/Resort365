<?php

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\AuditTrail;
use Modules\Procurement\Actions\SavePurchaseRequisition;
use Modules\Procurement\Actions\WithdrawPurchaseRequisition;
use Modules\Procurement\Enums\PurchaseRequisitionStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Http\Controllers\Concerns\ProcurementScreen;
use Modules\Procurement\Http\Requests\RequisitionRequest;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Services\ItemLabels;
use Modules\Procurement\Services\Lookups;

/**
 * Procurement → Requisitions: a department asks to buy items; once approved it goes to an RFQ or straight to an order.
 */
class RequisitionController extends Controller
{
    use ProcurementScreen;

    public function index(Request $request, Lookups $lookups): View
    {
        Gate::authorize('viewAny', PurchaseRequisition::class);
        $status = PurchaseRequisitionStatus::tryFrom((string) $request->query('status'));

        return view('procurement::requisitions.index', [
            'requisitions' => PurchaseRequisition::query()->where('property_id', $this->propertyId())->when($status instanceof PurchaseRequisitionStatus, fn ($query) => $query->where('status', $status?->value))->orderByDesc('id')->limit(100)->get(),
            'departments' => $lookups->departments(), 'statuses' => PurchaseRequisitionStatus::cases(), 'status' => $status?->value, 'subtitle' => $this->propertyName(),
            'canCreate' => $request->user()?->can('procurement.requisition.create') ?? false,
        ]);
    }

    public function create(Lookups $lookups): View
    {
        Gate::authorize('create', PurchaseRequisition::class);

        return view('procurement::requisitions.create', ['departments' => $lookups->departments(), 'items' => $lookups->items(), 'lines' => old('lines', [['item_id' => '', 'quantity' => '', 'estimated_unit_cost' => '']]), 'subtitle' => $this->propertyName()]);
    }

    public function store(RequisitionRequest $request, SavePurchaseRequisition $save): RedirectResponse
    {
        Gate::authorize('create', PurchaseRequisition::class);

        try {
            $requisition = $save->handle($this->propertyId(), $request->filled('department_id') ? (int) $request->validated('department_id') : null, $request->lines(), $this->userId(),
                $request->filled('needed_on') ? (string) $request->validated('needed_on') : null, $request->filled('notes') ? (string) $request->validated('notes') : null);
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.requisitions.show', $requisition)->with('success', $requisition->status === PurchaseRequisitionStatus::Approved
            ? __('Requisition :no approved: it can go to an RFQ or an order.', ['no' => $requisition->requisition_no]) : __('Requisition :no sent for approval.', ['no' => $requisition->requisition_no]));
    }

    public function show(Request $request, PurchaseRequisition $requisition, ApprovalEngine $approvals, AuditTrail $audit, Lookups $lookups, ItemLabels $labels): View
    {
        Gate::authorize('view', $requisition);
        $requisition->load('lines');
        $approved = $requisition->status === PurchaseRequisitionStatus::Approved;

        return view('procurement::requisitions.show', [
            'requisition' => $requisition, 'items' => $labels->for($requisition->lines->pluck('item_id')), 'department' => $lookups->departments()[$requisition->department_id] ?? null,
            'approval' => $requisition->approval_request_id !== null ? $approvals->find($requisition->approval_request_id) : null, 'trail' => $audit->for($requisition),
            'canWithdraw' => in_array($requisition->status, [PurchaseRequisitionStatus::Pending, PurchaseRequisitionStatus::Approved], true) && Gate::allows('withdraw', $requisition),
            'canRfq' => $approved && ($request->user()?->can('procurement.rfq.manage') ?? false), 'canOrder' => $approved && Gate::allows('order', $requisition),
            'vendors' => $lookups->vendors(), 'stores' => $lookups->stores(),
            'rfqs' => Rfq::query()->where('purchase_requisition_id', $requisition->id)->orderByDesc('id')->get(), 'orders' => PurchaseOrder::query()->where('purchase_requisition_id', $requisition->id)->orderByDesc('id')->get(),
            'subtitle' => $this->propertyName(),
        ]);
    }

    public function withdraw(PurchaseRequisition $requisition, WithdrawPurchaseRequisition $withdraw): RedirectResponse
    {
        Gate::authorize('withdraw', $requisition);

        try {
            $withdraw->handle($requisition, $this->userId());
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('procurement.requisitions.show', $requisition)->with('success', __('Requisition withdrawn.'));
    }
}
