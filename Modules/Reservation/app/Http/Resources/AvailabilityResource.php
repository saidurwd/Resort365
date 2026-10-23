<?php

namespace Modules\Reservation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\DTOs\AvailabilityResult;
use Modules\Reservation\DTOs\CottageOption;
use Modules\Reservation\DTOs\PriceQuote;
use Modules\Reservation\DTOs\RestrictionViolation;
use Modules\Reservation\DTOs\RoomTypeOption;

/**
 * What one rate plan can sell for a stay (Step 8.1): room types with their free rooms, and cottages free whole. `bookable` is false
 * when the party does not fit or a restriction (minimum stay, closed to arrival…) applies; `restrictions` says which.
 *
 * @mixin AvailabilityResult
 */
class AvailabilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $why = fn (array $violations): array => array_map(fn (RestrictionViolation $violation): array => ['restriction' => $violation->restriction->value, 'message' => $violation->message()], $violations);

        return [
            'rate_plan' => ['id' => $this->ratePlan->id, 'code' => $this->ratePlan->code, 'name' => $this->ratePlan->name, 'meal_plan' => $this->ratePlan->mealPlan->value, 'refundable' => $this->ratePlan->isRefundable],
            'plan_restrictions' => $why($this->planViolations),
            'room_types' => array_map(fn (RoomTypeOption $option): array => [
                'room_type_id' => $option->roomType->id,
                'code' => $option->roomType->code,
                'name' => $option->roomType->name,
                'max_occupancy' => $option->roomType->maxOccupancy,
                'free_rooms' => array_map(fn (RoomSummary $room): array => ['id' => $room->id, 'number' => $room->number], $option->rooms),
                'fits_party' => $option->fits,
                'bookable' => $option->fits && $option->isBookable(),
                'restrictions' => $why($option->violations),
                'price' => $option->quote instanceof PriceQuote ? new PriceQuoteResource($option->quote)->toArray($request) : null,
            ], $this->roomTypes),
            'cottages' => array_map(fn (CottageOption $option): array => [
                'cottage_id' => $option->cottage->id,
                'code' => $option->cottage->code,
                'name' => $option->cottage->name,
                'type' => $option->typeName,
                'rooms' => $option->roomNumbers,
                'max_occupancy' => $option->cottage->maxOccupancy,
                'fits_party' => $option->fits,
                'bookable' => $option->fits && $option->isBookable(),
                'restrictions' => $why($option->violations),
                'price' => $option->quote instanceof PriceQuote ? new PriceQuoteResource($option->quote)->toArray($request) : null,
            ], $this->cottages),
        ];
    }
}
