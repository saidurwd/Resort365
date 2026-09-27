<?php

namespace App\Support\Tenancy;

use LogicException;

/**
 * Thrown when code tries to write a record that belongs to a different tenant.
 */
class TenantMismatch extends LogicException
{
    public static function forModel(string $model, int|string|null $key, int $recordTenant, int $currentTenant): self
    {
        return new self("Record [{$model}#{$key}] belongs to tenant {$recordTenant}, but the current tenant is {$currentTenant}.");
    }
}
