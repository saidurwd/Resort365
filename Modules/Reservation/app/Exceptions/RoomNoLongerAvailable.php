<?php

namespace Modules\Reservation\Exceptions;

use App\Support\Api\ApiError;
use RuntimeException;

/**
 * Another booking took a room of this booking first (the unique lock index refused it). Nothing
 * was saved. $taken lists room number => dates (Y-m-d).
 */
class RoomNoLongerAvailable extends RuntimeException implements ApiError
{
    /**
     * @param  array<string, list<string>>  $taken
     */
    public function __construct(public readonly array $taken)
    {
        parent::__construct(__('No longer available: :rooms.', ['rooms' => implode('; ', array_map(
            fn (string $room, array $dates): string => __('room :room on :dates', ['room' => $room, 'dates' => implode(', ', $dates)]),
            array_keys($taken), $taken,
        ))]));
    }

    public function apiStatus(): int
    {
        return 409;
    }

    public function apiCode(): string
    {
        return 'room_no_longer_available';
    }

    public function apiDetails(): array
    {
        return ['taken' => $this->taken];
    }
}
