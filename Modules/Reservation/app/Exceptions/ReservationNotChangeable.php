<?php

namespace Modules\Reservation\Exceptions;

use App\Support\Api\ApiError;
use RuntimeException;

/**
 * The reservation's status does not allow this change (e.g. modifying a cancelled booking).
 */
class ReservationNotChangeable extends RuntimeException implements ApiError
{
    public function apiStatus(): int
    {
        return 409;
    }

    public function apiCode(): string
    {
        return 'reservation_not_changeable';
    }

    public function apiDetails(): array
    {
        return [];
    }
}
