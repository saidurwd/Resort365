<?php

namespace Modules\Reservation\Services;

use Modules\Property\Contracts\InventoryCatalog;
use Modules\Reservation\Models\ReservationItem;

/**
 * Readable names of a property's bookable units ("Room 401", "Sunset Villa (whole cottage)"),
 * keyed "room:{id}" and "cottage:{id}", for screens and the reservation history.
 */
class ItemLabels
{
    /**
     * @var array<int, array<string, string>> per property (per request)
     */
    private array $labels = [];

    public function __construct(private readonly InventoryCatalog $catalog) {}

    /**
     * @return array<string, string>
     */
    public function forProperty(int $propertyId): array
    {
        if (! isset($this->labels[$propertyId])) {
            $labels = [];

            foreach ($this->catalog->cottages($propertyId) as $cottage) {
                $labels['cottage:'.$cottage->id] = __(':name (whole cottage)', ['name' => $cottage->name]);
            }

            foreach ($this->catalog->rooms($propertyId) as $room) {
                $labels['room:'.$room->id] = __('Room :number', ['number' => $room->number]);
            }

            $this->labels[$propertyId] = $labels;
        }

        return $this->labels[$propertyId];
    }

    public function of(ReservationItem $item): string
    {
        $key = $item->room_id !== null ? 'room:'.$item->room_id : 'cottage:'.$item->cottage_id;

        return $this->forProperty($item->property_id)[$key] ?? $key;
    }
}
