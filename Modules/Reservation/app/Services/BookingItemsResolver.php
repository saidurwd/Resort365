<?php

namespace Modules\Reservation\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\DTOs\AvailabilityResult;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\CottageOption;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\DTOs\RestrictionViolation;
use Modules\Reservation\DTOs\RoomTypeOption;
use Modules\Reservation\Enums\ItemType;

/**
 * Turns the rooms and cottages a client asks for into BookingItems, checked against availability the way the booking wizard checks
 * a choice (Step 8.1; the public website in 8.2 uses it too): each unit must be free, fit its party and have no restriction for
 * the stay. A room may be asked for by number id or by room type, when the first free room of the type is taken. Problems are a
 * ValidationException on `items.N` (or `rate_plan_id`), never a half-made booking.
 */
class BookingItemsResolver
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  list<array{type: string, room_id?: int|null, room_type_id?: int|null, cottage_id?: int|null, adults: int, children?: int|null}>  $rows
     * @return list<BookingItem>
     *
     * @throws ValidationException
     */
    public function resolve(int $propertyId, int $ratePlanId, CarbonImmutable $checkIn, CarbonImmutable $checkOut, array $rows, ?string $promoCode = null, string $channel = 'front_desk'): array
    {
        /** @var array<string, AvailabilityResult> $results */
        $results = [];
        $usedRooms = [];
        $items = [];
        $errors = [];

        // Whole cottages first, so a room asked for by type never takes a room of a cottage booked whole in the same stay.
        $order = array_keys($rows);
        usort($order, fn (int $a, int $b): int => [($rows[$a]['type'] ?? '') === ItemType::Cottage->value ? 0 : 1, $a] <=> [($rows[$b]['type'] ?? '') === ItemType::Cottage->value ? 0 : 1, $b]);

        foreach ($order as $index) {
            $row = $rows[$index];
            $adults = max(1, (int) $row['adults']);
            $children = max(0, (int) ($row['children'] ?? 0));
            $party = $adults.'+'.$children;
            $result = $results[$party] ??= $this->search($propertyId, $ratePlanId, $checkIn, $checkOut, new Occupancy($adults, $children), $promoCode, $channel);

            if ($result->planViolations !== []) {
                throw ValidationException::withMessages(['rate_plan_id' => implode(' ', array_map(fn (RestrictionViolation $violation): string => $violation->message(), $result->planViolations))]);
            }

            $type = ItemType::tryFrom((string) $row['type']);
            $unit = match ($type) {
                ItemType::Cottage => $this->cottage($result, (int) ($row['cottage_id'] ?? 0)),
                ItemType::Room => $this->room($result, isset($row['room_id']) ? (int) $row['room_id'] : null, isset($row['room_type_id']) ? (int) $row['room_type_id'] : null, $usedRooms),
                null => __('Choose a room or a cottage.'),
            };

            if (is_string($unit)) {
                $errors["items.{$index}"] = $unit;

                continue;
            }

            if ($type === ItemType::Room) {
                $usedRooms[] = $unit;
            } elseif ($type === ItemType::Cottage) {
                $cottage = collect($result->cottages)->first(fn (CottageOption $option): bool => $option->cottage->id === $unit);
                array_push($usedRooms, ...($cottage instanceof CottageOption ? $cottage->cottage->roomIds : []));
            }

            $items[$index] = new BookingItem($type ?? ItemType::Room, $unit, $ratePlanId, $adults, $children);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        ksort($items);

        return array_values($items);
    }

    private function search(int $propertyId, int $ratePlanId, CarbonImmutable $checkIn, CarbonImmutable $checkOut, Occupancy $party, ?string $promoCode, string $channel): AvailabilityResult
    {
        return $this->availability->search(new AvailabilitySearch($propertyId, $ratePlanId, $checkIn, $checkOut, $party, $promoCode, $channel))
            ?? throw ValidationException::withMessages(['rate_plan_id' => __('This rate plan cannot be booked here.')]);
    }

    /**
     * The cottage's id, or why it cannot be booked whole.
     */
    private function cottage(AvailabilityResult $result, int $cottageId): int|string
    {
        $option = collect($result->cottages)->first(fn (CottageOption $option): bool => $option->cottage->id === $cottageId);

        return match (true) {
            ! $option instanceof CottageOption => __('This cottage is not free whole for these dates.'),
            ! $option->fits => __('The party is too large for :name.', ['name' => $option->cottage->name]),
            ! $option->isBookable() => $this->why($option->violations, $option->cottage->name),
            default => $cottageId,
        };
    }

    /**
     * The room's id (the asked room, or the first free one of the type not already taken), or why it cannot be booked.
     *
     * @param  list<int>  $usedRooms
     */
    private function room(AvailabilityResult $result, ?int $roomId, ?int $roomTypeId, array $usedRooms): int|string
    {
        foreach ($result->roomTypes as $option) {
            /** @var RoomTypeOption $option */
            $rooms = array_values(array_filter($option->rooms, fn (RoomSummary $room): bool => ! in_array($room->id, $usedRooms, true)));
            $match = $roomId !== null
                ? collect($rooms)->first(fn (RoomSummary $room): bool => $room->id === $roomId)
                : ($option->roomType->id === $roomTypeId ? ($rooms[0] ?? null) : null);

            if ($roomId === null && $option->roomType->id === $roomTypeId && ! $match instanceof RoomSummary) {
                return __('No :type room is free for these dates.', ['type' => $option->roomType->name]);
            }

            if (! $match instanceof RoomSummary) {
                continue;
            }

            return match (true) {
                ! $option->fits => __('The party is too large for a :type room.', ['type' => $option->roomType->name]),
                ! $option->isBookable() => $this->why($option->violations, $option->roomType->name),
                default => $match->id,
            };
        }

        return $roomId !== null ? __('This room is not free for these dates.') : __('No room of this type is free for these dates.');
    }

    /**
     * @param  list<RestrictionViolation>  $violations
     */
    private function why(array $violations, string $unit): string
    {
        return $violations === []
            ? __(':unit has no rate for these dates.', ['unit' => $unit])
            : $unit.': '.implode(' ', array_map(fn (RestrictionViolation $violation): string => $violation->message(), $violations));
    }
}
