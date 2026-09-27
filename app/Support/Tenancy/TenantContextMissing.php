<?php

namespace App\Support\Tenancy;

use LogicException;

/**
 * Thrown when tenant-owned data is used without a current tenant.
 * Wrap console, seeder and job code in TenantContext::run().
 */
class TenantContextMissing extends LogicException
{
    public static function forModel(string $model): self
    {
        return new self("No tenant is set while using the tenant-owned model [{$model}]. Wrap the code in TenantContext::run().");
    }

    public static function forService(string $service): self
    {
        return new self("No tenant is set while using [{$service}]. Wrap the code in TenantContext::run().");
    }
}
