<?php

namespace Modules\Property\Exceptions;

use RuntimeException;

/**
 * A setup record is still in use and cannot be deleted. The message is shown to the user.
 */
class CannotDelete extends RuntimeException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
