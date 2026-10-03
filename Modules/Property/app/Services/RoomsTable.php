<?php

namespace Modules\Property\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Property\Models\Room;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of one property's rooms.
 */
class RoomsTable
{
    public function __construct(
        private readonly DataTables $dataTables,
        private readonly OccupancyCalculator $occupancy,
    ) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'number', 'title' => __('Room')],
            ['data' => 'cottage', 'title' => __('Cottage'), 'orderable' => false, 'searchable' => false],
            ['data' => 'type', 'title' => __('Room type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'floor', 'title' => __('Floor')],
            ['data' => 'capacity', 'title' => __('Adults / children'), 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'max_occupancy', 'title' => __('Max guests'), 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'housekeeping_status', 'title' => __('Housekeeping'), 'searchable' => false],
            ['data' => 'is_active', 'title' => __('Active'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
        ];
    }

    public function toJson(int $propertyId): JsonResponse
    {
        $query = Room::query()->select('rooms.*')->where('property_id', $propertyId)->with(['cottage', 'roomType']);

        return $this->dataTables->eloquent($query)
            ->editColumn('number', fn (Room $room): string => '<span class="fw-semibold">'.e($room->number).'</span>'
                .($room->name !== null ? '<div class="small text-body-secondary">'.e($room->name).'</div>' : ''))
            ->addColumn('cottage', fn (Room $room): string => '<a href="'.e(route('property.cottages.show', $room->cottage)).'">'.e($room->cottage->name).'</a>')
            ->addColumn('type', fn (Room $room): string => $room->roomType->name)
            ->editColumn('floor', fn (Room $room): string => $room->floor ?? '—')
            ->addColumn('capacity', function (Room $room): string {
                $capacity = $this->occupancy->forRoom($room);

                return $capacity->maxAdults.' / '.$capacity->maxChildren;
            })
            ->addColumn('max_occupancy', fn (Room $room): int => $this->occupancy->forRoom($room)->maxOccupancy)
            ->editColumn('housekeeping_status', fn (Room $room): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $room->housekeeping_status]))
            ->editColumn('is_active', fn (Room $room): string => $room->is_active
                ? '<span class="badge text-bg-success">'.e(__('Yes')).'</span>'
                : '<span class="badge text-bg-secondary">'.e(__('No')).'</span>')
            ->addColumn('actions', fn (Room $room): string => view('property::rooms.partials.actions', ['room' => $room])->render())
            ->rawColumns(['number', 'cottage', 'housekeeping_status', 'is_active', 'actions'])
            ->toJson();
    }
}
