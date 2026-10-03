<?php

namespace Modules\Guest\Exceptions;

use RuntimeException;

/**
 * Two profiles cannot be merged; the message is shown to the user.
 */
class CannotMergeGuests extends RuntimeException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
