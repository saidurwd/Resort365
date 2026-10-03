<?php

namespace App\Support\Tenancy;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a record is written for a property the user may not access.
 */
class PropertyAccessDenied extends HttpException
{
    public static function forModel(string $model, int $propertyId): self
    {
        return new self(403, "You may not change [{$model}] records of property {$propertyId}.");
    }
}
