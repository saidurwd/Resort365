<?php

namespace Modules\Property\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Property\Models\Cottage;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of one property's cottages.
 */
class CottagesTable
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
            ['data' => 'code', 'title' => __('Code')],
            ['data' => 'name', 'title' => __('Cottage')],
            ['data' => 'type', 'title' => __('Type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'zone', 'title' => __('Zone')],
            ['data' => 'booking_mode', 'title' => __('Booking mode'), 'searchable' => false],
            ['data' => 'rooms_count', 'title' => __('Rooms'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'max_occupancy', 'title' => __('Max guests'), 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'status', 'title' => __('Status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
        ];
    }

    public function toJson(int $propertyId): JsonResponse
    {
        $query = Cottage::query()->select('cottages.*')->where('property_id', $propertyId)
            ->with(['cottageType', 'rooms.roomType'])->withCount('rooms');

        return $this->dataTables->eloquent($query)
            ->editColumn('name', fn (Cottage $cottage): string => '<a href="'.e(route('property.cottages.show', $cottage)).'" class="fw-semibold">'.e($cottage->name).'</a>')
            ->addColumn('type', fn (Cottage $cottage): string => e($cottage->cottageType->name))
            ->editColumn('zone', fn (Cottage $cottage): string => e($cottage->zone ?? '—'))
            ->editColumn('booking_mode', fn (Cottage $cottage): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $cottage->booking_mode]))
            ->addColumn('max_occupancy', fn (Cottage $cottage): int => $this->occupancy->forCottage($cottage))
            ->editColumn('status', fn (Cottage $cottage): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $cottage->status]))
            ->addColumn('actions', fn (Cottage $cottage): string => view('property::cottages.partials.actions', ['cottage' => $cottage])->render())
            ->rawColumns(['name', 'booking_mode', 'status', 'actions'])
            ->toJson();
    }
}
