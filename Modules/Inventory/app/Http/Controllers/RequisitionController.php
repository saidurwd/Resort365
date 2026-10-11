<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\AuditTrail;
use Modules\Inventory\Actions\IssueRequisition;
use Modules\Inventory\Actions\SubmitRequisition;
use Modules\Inventory\Actions\WithdrawRequisition;
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Http\Requests\RequisitionRequest;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Models\Store;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * Inventory → Requisitions: a department asks a store for items, an approver agrees, the store keeper issues.
 */
class RequisitionController extends Controller
{
    public function index(Request $request, PropertyContext $property, DepartmentDirectory $departments): View
    {
        Gate::authorize('viewAny', StockRequisition::class);
        $names = collect($departments->all(false))->mapWithKeys(fn ($department): array => [$department->id => $department->name])->all();

        return view('inventory::requisitions.index', [
            'requisitions' => StockRequisition::query()->with('store')->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))
                ->when(RequisitionStatus::tryFrom((string) $request->query('status')) instanceof RequisitionStatus, fn ($query) => $query->where('status', $request->query('status')))
                ->orderByDesc('id')->limit(100)->get(),
            'departments' => $names, 'statuses' => collect(RequisitionStatus::cases())->mapWithKeys(fn (RequisitionStatus $status): array => [$status->value => $status->label()])->all(),
            'canCreate' => $request->user()?->can('inventory.requisition.create') ?? false,
        ]);
    }

    public function create(PropertyContext $property, DepartmentDirectory $departments): View
    {
        Gate::authorize('create', StockRequisition::class);

        return view('inventory::requisitions.create', [
            'stores' => Store::query()->where('is_active', true)->when($property->currentId() !== null, fn ($query) => $query->where('property_id', $property->currentId()))->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => collect($departments->all())->mapWithKeys(fn ($department): array => [$department->id => $department->name])->all(),
            'items' => Item::query()->where('is_stockable', true)->where('is_active', true)->with('baseUnit')->orderBy('name')->get()->mapWithKeys(fn (Item $item): array => [$item->id => $item->sku.' · '.$item->name.' ('.$item->baseUnit->code.')'])->all(),
            'lines' => old('lines', [['item_id' => '', 'quantity' => '']]),
        ]);
    }

    public function store(RequisitionRequest $request, SubmitRequisition $submit): RedirectResponse
    {
        Gate::authorize('create', StockRequisition::class);

        try {
            $requisition = $submit->handle(
                (int) $request->validated('store_id'), (int) $request->validated('department_id'), $request->lines(), (int) $request->user()?->getAuthIdentifier(),
                $request->filled('needed_on') ? (string) $request->validated('needed_on') : null, $request->filled('notes') ? (string) $request->validated('notes') : null,
            );
        } catch (StockRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('inventory.requisitions.show', $requisition)->with('success', $requisition->status === RequisitionStatus::Approved
            ? __('Requisition :no approved: the store can issue it.', ['no' => $requisition->requisition_no]) : __('Requisition :no sent for approval.', ['no' => $requisition->requisition_no]));
    }

    public function show(StockRequisition $requisition, ApprovalEngine $approvals, AuditTrail $audit, DepartmentDirectory $departments): View
    {
        Gate::authorize('view', $requisition);
        $requisition->load(['store', 'lines.item.baseUnit']);
        $available = StockBalance::query()->where('store_id', $requisition->store_id)->whereIn('item_id', $requisition->lines->pluck('item_id'))->pluck('quantity', 'item_id');

        return view('inventory::requisitions.show', [
            'requisition' => $requisition, 'department' => $departments->find($requisition->department_id)?->name, 'approval' => $requisition->approval_request_id !== null ? $approvals->find($requisition->approval_request_id) : null,
            'available' => $available, 'trail' => $audit->for($requisition),
            'canIssue' => $requisition->status === RequisitionStatus::Approved && Gate::allows('issue', $requisition),
            'canWithdraw' => in_array($requisition->status, [RequisitionStatus::Pending, RequisitionStatus::Approved], true) && Gate::allows('withdraw', $requisition),
        ]);
    }

    public function issue(Request $request, StockRequisition $requisition, IssueRequisition $issue): RedirectResponse
    {
        Gate::authorize('issue', $requisition);
        $request->validate(['quantities' => ['nullable', 'array'], 'quantities.*' => ['nullable', 'numeric', 'min:0']]);
        $quantities = [];

        foreach ((array) $request->input('quantities', []) as $itemId => $value) {
            if ($value !== null && $value !== '') {
                $quantities[(int) $itemId] = (string) $value;
            }
        }

        try {
            $issue->handle($requisition, $quantities, (int) $request->user()?->getAuthIdentifier());
        } catch (StockRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('inventory.requisitions.show', $requisition)->with('success', __('Requisition issued: the stock is out of :store.', ['store' => $requisition->store->name]));
    }

    public function withdraw(Request $request, StockRequisition $requisition, WithdrawRequisition $withdraw): RedirectResponse
    {
        Gate::authorize('withdraw', $requisition);

        try {
            $withdraw->handle($requisition, (int) $request->user()?->getAuthIdentifier());
        } catch (StockRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('inventory.requisitions.show', $requisition)->with('success', __('Requisition withdrawn.'));
    }
}
