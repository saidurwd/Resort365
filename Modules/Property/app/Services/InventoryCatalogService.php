<?php

namespace Modules\Property\Services;

use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Property\Enums\UnitKind;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Room;
use Modules\Property\Models\RoomType;

class InventoryCatalogService implements InventoryCatalog
{
    public function __construct(private readonly OccupancyCalculator $occupancy) {}

    public function rooms(int $propertyId): array
    {
        return Room::query()->where('property_id', $propertyId)->with('roomType')->orderBy('sort_order')->orderBy('number')->get()
            ->map(function (Room $room): RoomSummary {
                $capacity = $this->occupancy->forRoom($room);

                return new RoomSummary($room->id, $room->property_id, $room->cottage_id, $room->room_type_id, $room->number, $room->name,
                    $room->roomType->base_occupancy, $capacity->maxAdults, $capacity->maxChildren, $capacity->maxOccupancy, $room->is_active);
            })->values()->all();
    }

    public function cottages(int $propertyId): array
    {
        return Cottage::query()->where('property_id', $propertyId)->with('rooms.roomType')->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Cottage $cottage): CottageSummary => new CottageSummary(
                $cottage->id, $cottage->property_id, $cottage->cottage_type_id, $cottage->code, $cottage->name, $cottage->booking_mode,
                $this->occupancy->forCottage($cottage), $cottage->rooms->where('is_active', true)->pluck('id')->map(intval(...))->values()->all(),
                $cottage->isActive(),
            ))->values()->all();
    }

    public function unitTypes(int $propertyId, bool $activeOnly = false): array
    {
        $rooms = RoomType::query()->where('property_id', $propertyId)->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get()->map(fn (RoomType $type): UnitTypeSummary => $this->room($type));
        $cottages = CottageType::query()->where('property_id', $propertyId)->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get()->map(fn (CottageType $type): UnitTypeSummary => $this->cottage($type));

        return [...$rooms->values()->all(), ...$cottages->values()->all()];
    }

    public function find(UnitKind $kind, int $id): ?UnitTypeSummary
    {
        return match ($kind) {
            UnitKind::RoomType => ($type = RoomType::query()->find($id)) instanceof RoomType ? $this->room($type) : null,
            UnitKind::CottageType => ($type = CottageType::query()->find($id)) instanceof CottageType ? $this->cottage($type) : null,
        };
    }

    private function room(RoomType $type): UnitTypeSummary
    {
        return new UnitTypeSummary(UnitKind::RoomType, $type->id, $type->property_id, $type->code, $type->name,
            $type->base_occupancy, $type->max_occupancy, $type->max_adults, $type->max_children, $type->is_active);
    }

    private function cottage(CottageType $type): UnitTypeSummary
    {
        return new UnitTypeSummary(UnitKind::CottageType, $type->id, $type->property_id, $type->code, $type->name,
            $type->max_occupancy, $type->max_occupancy, null, null, $type->is_active);
    }
}
