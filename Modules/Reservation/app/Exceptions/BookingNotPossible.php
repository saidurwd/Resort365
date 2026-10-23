<?php

namespace Modules\Reservation\Exceptions;

use App\Support\Api\ApiError;
use RuntimeException;

/**
 * The booking cannot be made as asked (no rate, unknown or doubled unit…); the message is shown.
 */
class BookingNotPossible extends RuntimeException implements ApiError
{
    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'booking_not_possible';
    }

    public function apiDetails(): array
    {
        return [];
    }
}
