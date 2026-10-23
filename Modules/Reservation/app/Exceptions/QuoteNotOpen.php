<?php

namespace Modules\Reservation\Exceptions;

use App\Support\Api\ApiError;
use RuntimeException;

/**
 * The quote is booked, declined or past its validity date, so it cannot be sent or booked.
 */
class QuoteNotOpen extends RuntimeException implements ApiError
{
    public function apiStatus(): int
    {
        return 409;
    }

    public function apiCode(): string
    {
        return 'quote_not_open';
    }

    public function apiDetails(): array
    {
        return [];
    }
}
