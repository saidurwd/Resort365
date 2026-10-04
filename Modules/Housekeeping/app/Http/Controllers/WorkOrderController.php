<?php

namespace Modules\Housekeeping\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Housekeeping\Actions\ReportWorkOrder;
use Modules\Housekeeping\Actions\UpdateWorkOrder;
use Modules\Housekeeping\DTOs\WorkOrderData;
use Modules\Housekeeping\Enums\WorkOrderCategory;
use Modules\Housekeeping\Enums\WorkOrderPriority;
use Modules\Housekeeping\Enums\WorkOrderStatus;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Http\Controllers\Concerns\HousekeepingScreen;
use Modules\Housekeeping\Http\Requests\ReportWorkOrderRequest;
use Modules\Housekeeping\Http\Requests\UpdateWorkOrderRequest;
use Modules\Housekeeping\Models\MaintenanceRequest;
use Modules\Housekeeping\Services\WorkOrdersTable;

/**
 * Housekeeping → Work orders (maintenance): the list, reporting a fault (any staff member), and
 * working an order (managers, or the technician it is assigned to).
 */
class WorkOrderController extends Controller
{
    use HousekeepingScreen;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MaintenanceRequest::class);

        return view('housekeeping::work-orders.index', ['columns' => WorkOrdersTable::columns(), 'all' => $request->boolean('all'), 'property' => $this->property()]);
    }

    public function data(Request $request, WorkOrdersTable $table): JsonResponse
    {
        Gate::authorize('viewAny', MaintenanceRequest::class);

        return $table->toJson($this->property()->id, $request->boolean('all'));
    }

    public function create(): View
    {
        Gate::authorize('create', MaintenanceRequest::class);
        $property = $this->property();

        return view('housekeeping::work-orders.create', [
            'property' => $property,
            'rooms' => $this->roomOptions($property->id),
            'categories' => WorkOrderCategory::options(),
            'priorities' => WorkOrderPriority::options(),
            'mine' => MaintenanceRequest::query()->where('property_id', $property->id)->where('reported_by', $this->userId())->latest()->limit(10)->get(),
        ]);
    }

    public function store(ReportWorkOrderRequest $request, ReportWorkOrder $report): RedirectResponse
    {
        try {
            $order = $report->handle(new WorkOrderData(
                $this->property()->id, (string) $request->validated('title'), WorkOrderCategory::from((string) $request->validated('category')),
                WorkOrderPriority::from((string) $request->validated('priority')), $request->filled('room_id') ? (int) $request->validated('room_id') : null,
                $request->validated('location'), $request->validated('description'), $this->userId(),
            ));
        } catch (HousekeepingNotPossible $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('housekeeping.work-orders.show', $order)->with('success', __('Work order WO-:id reported.', ['id' => $order->id]));
    }

    public function show(MaintenanceRequest $order): View
    {
        Gate::authorize('view', $order);

        return view('housekeeping::work-orders.show', [
            'order' => $order,
            'property' => $this->property(),
            'rooms' => $this->roomOptions($order->property_id),
            'names' => $this->userNames(),
            'technicians' => $this->staffWith('housekeeping.work-order.view'),
            'statuses' => WorkOrderStatus::options(),
            'priorities' => WorkOrderPriority::options(),
            'canUpdate' => auth()->user()?->can('update', $order) ?? false,
        ]);
    }

    public function update(UpdateWorkOrderRequest $request, MaintenanceRequest $order, UpdateWorkOrder $update): RedirectResponse
    {
        try {
            $update->handle($order, WorkOrderStatus::from((string) $request->validated('status')), WorkOrderPriority::from((string) $request->validated('priority')),
                $request->filled('assigned_to') ? (int) $request->validated('assigned_to') : null, (string) $request->validated('labour_cost'),
                (string) $request->validated('parts_cost'), $request->validated('resolution'));
        } catch (HousekeepingNotPossible $exception) {
            return to_route('housekeeping.work-orders.show', $order)->withInput()->with('error', $exception->getMessage());
        }

        return to_route('housekeeping.work-orders.show', $order)->with('success', __('Work order updated.'));
    }
}
