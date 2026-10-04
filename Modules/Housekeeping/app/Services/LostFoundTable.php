<?php

namespace Modules\Housekeeping\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Housekeeping\Models\LostFoundItem;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\RoomSummary;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of one property's lost & found register, newest first.
 */
class LostFoundTable
{
    public function __construct(
        private readonly DataTables $dataTables,
        private readonly InventoryCatalog $catalog,
        private readonly GuestLookup $guests,
    ) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'found_on', 'title' => __('Found'), 'searchable' => false],
            ['data' => 'description', 'title' => __('Item')],
            ['data' => 'found_at', 'title' => __('Where')],
            ['data' => 'stored_at', 'title' => __('Kept at')],
            ['data' => 'status', 'title' => __('Status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
        ];
    }

    public function toJson(int $propertyId, bool $canManage): JsonResponse
    {
        $rooms = collect($this->catalog->rooms($propertyId))->mapWithKeys(fn (RoomSummary $room): array => [$room->id => $room->number]);

        return $this->dataTables->eloquent(LostFoundItem::query()->where('property_id', $propertyId))
            ->editColumn('found_on', fn (LostFoundItem $item): string => $item->found_on->format('d M Y'))
            ->editColumn('description', fn (LostFoundItem $item): string => e($item->description))
            ->editColumn('found_at', fn (LostFoundItem $item): string => e($item->found_at.($item->room_id !== null ? ' · '.__('Room :number', ['number' => $rooms[$item->room_id] ?? '?']) : '')))
            ->editColumn('stored_at', fn (LostFoundItem $item): string => e((string) $item->stored_at))
            ->editColumn('status', function (LostFoundItem $item): string {
                $to = $item->guest_id !== null ? $this->guests->find($item->guest_id)?->name : $item->claimed_by_name;

                return Blade::render('<x-status-badge :status="$status" />', ['status' => $item->status]).($to ? ' <span class="small text-body-secondary">'.e($to).'</span>' : '');
            })
            ->addColumn('actions', fn (LostFoundItem $item): string => $canManage ? view('housekeeping::lost-found.partials.actions', ['item' => $item])->render() : '')
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }
}
