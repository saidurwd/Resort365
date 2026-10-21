<?php

namespace App\Support\Tenancy;

use RuntimeException;

/**
 * A tenant tried to go past what its plan allows (Step 7.3): one more room, user, property or file than the plan includes. The
 * message is for the person doing it and says what to do. The application shows it as an ordinary error on the page they were on.
 */
class PlanLimitReached extends RuntimeException
{
    public function __construct(public readonly string $limit, public readonly int|float $max, string $message)
    {
        parent::__construct($message);
    }
}
