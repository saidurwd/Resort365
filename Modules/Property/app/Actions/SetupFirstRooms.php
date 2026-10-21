<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\PropertyContext;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\PlanLimits;
use Modules\Property\Models\Room;

/**
 * The cottages-and-rooms step of the setup checklist (Step 7.4): one room type and one cottage type, and the number of cottages asked
 * for, each with the same number of rooms, numbered on from 101 (a cottage with several rooms can be booked whole or by the room).
 * Only for a property with no rooms yet; the rooms must fit the plan. Anything finer is done in Setup → Cottages and Rooms.
 */
class SetupFirstRooms extends Action
{
    public function __construct(
        private readonly PropertyContext $property,
        private readonly PlanLimits $limits,
    ) {}

    /**
     * @return int how many rooms were made
     *
     * @throws ValidationException
     */
    public function handle(int $cottages, int $roomsPerCottage, string $roomTypeName, int $maxOccupancy): int
    {
        $propertyId = $this->property->currentId();

        if ($propertyId === null) {
            throw ValidationException::withMessages(['cottage_count' => __('Set up the property first.')]);
        }

        if (Room::query()->where('property_id', $propertyId)->exists()) {
            throw ValidationException::withMessages(['cottage_count' => __('This property already has rooms. Change them in Setup → Cottages and Rooms.')]);
        }

        $total = $cottages * $roomsPerCottage;
        $this->limits->ensureWithin('max_rooms', Room::query()->count() + $total);

        return $this->transaction(function () use ($propertyId, $cottages, $roomsPerCottage, $roomTypeName, $maxOccupancy, $total): int {
            $roomType = SaveRoomType::make()->handle(null, [
                'property_id' => $propertyId, 'code' => 'STD', 'name' => trim($roomTypeName), 'base_occupancy' => min(2, $maxOccupancy), 'max_adults' => $maxOccupancy,
                'max_children' => 0, 'max_occupancy' => $maxOccupancy, 'is_active' => true, 'amenity_ids' => [],
            ]);
            $cottageType = SaveCottageType::make()->handle(null, [
                'property_id' => $propertyId, 'code' => 'STD', 'name' => __('Standard cottage'), 'max_occupancy' => $maxOccupancy * $roomsPerCottage, 'bedrooms' => $roomsPerCottage,
                'is_active' => true, 'amenity_ids' => [],
            ]);

            for ($index = 0; $index < $cottages; $index++) {
                CreateCottageWithRooms::make()->handle([
                    'property_id' => $propertyId, 'cottage_type_id' => $cottageType->id, 'code' => 'C'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), 'name' => __('Cottage :number', ['number' => $index + 1]),
                    'booking_mode' => $roomsPerCottage > 1 ? 'both' : 'rooms_only', 'status' => 'active', 'sort_order' => $index,
                    'room_count' => $roomsPerCottage, 'room_type_id' => $roomType->id, 'first_room_number' => (string) (101 + $index * $roomsPerCottage), 'floor' => null,
                ]);
            }

            return $total;
        });
    }
}
