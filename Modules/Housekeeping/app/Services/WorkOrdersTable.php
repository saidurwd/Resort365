<?php

namespace Modules\Housekeeping\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Housekeeping\Enums\WorkOrderStatus;
use Modules\Housekeeping\Models\MaintenanceRequest;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\RoomSummary;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of one property's work orders: open ones (default) or all.
 */
class WorkOrdersTable
{
    public function __construct(
        private readonly DataTables $dataTables,
        private readonly UserDirectory $users,
        private readonly InventoryCatalog $catalog,
    ) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'id', 'title' => __('No.'), 'searchable' => false],
            ['data' => 'title', 'title' => __('Fault')],
            ['data' => 'where', 'title' => __('Where'), 'orderable' => false, 'searchable' => false],
            ['data' => 'category', 'title' => __('Category'), 'searchable' => false],
            ['data' => 'priority', 'title' => __('Priority'), 'searchable' => false],
            ['data' => 'status', 'title' => __('Status'), 'searchable' => false],
            ['data' => 'technician', 'title' => __('Technician'), 'orderable' => false, 'searchable' => false],
            ['data' => 'created_at', 'title' => __('Reported'), 'searchable' => false],
        ];
    }

    public function toJson(int $propertyId, bool $all): JsonResponse
    {
        $names = collect($this->users->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name]);
        $rooms = collect($this->catalog->rooms($propertyId))->mapWithKeys(fn (RoomSummary $room): array => [$room->id => $room->number]);
        $query = MaintenanceRequest::query()->where('property_id', $propertyId)
            ->when(! $all, fn ($query) => $query->whereNotIn('status', [WorkOrderStatus::Done->value, WorkOrderStatus::Cancelled->value]));
        $badge = fn ($enum): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $enum]);

        return $this->dataTables->eloquent($query)
            ->editColumn('id', fn (MaintenanceRequest $order): string => '<a href="'.e(route('housekeeping.work-orders.show', $order)).'">WO-'.$order->id.'</a>')
            ->editColumn('title', fn (MaintenanceRequest $order): string => e($order->title))
            ->addColumn('where', fn (MaintenanceRequest $order): string => e($order->room_id !== null ? __('Room :number', ['number' => $rooms[$order->room_id] ?? '?']) : (string) $order->location))
            ->editColumn('category', fn (MaintenanceRequest $order): string => e($order->category->label()))
            ->editColumn('priority', fn (MaintenanceRequest $order): string => $badge($order->priority))
            ->editColumn('status', fn (MaintenanceRequest $order): string => $badge($order->status))
            ->addColumn('technician', fn (MaintenanceRequest $order): string => e($order->assigned_to !== null ? ($names[$order->assigned_to] ?? '') : '—'))
            ->editColumn('created_at', fn (MaintenanceRequest $order): string => $order->created_at?->format('d M Y H:i') ?? '')
            ->rawColumns(['id', 'priority', 'status'])
            ->toJson();
    }
}
