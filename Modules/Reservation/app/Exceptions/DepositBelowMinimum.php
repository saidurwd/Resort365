<?php

namespace Modules\Reservation\Exceptions;

use App\Support\Api\ApiError;
use RuntimeException;

/**
 * The deposit percent is outside the deposit policy's limits and the user may not override them.
 */
class DepositBelowMinimum extends RuntimeException implements ApiError
{
    public function apiStatus(): int
    {
        return 422;
    }

    public function apiCode(): string
    {
        return 'deposit_outside_policy';
    }

    public function apiDetails(): array
    {
        return [];
    }
}
