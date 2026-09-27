<?php

namespace App\Support\Authorization;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * spatie/laravel-permission "team" = the current tenant. An explicitly set team id
 * (setPermissionsTeamId) wins; otherwise the tenant from TenantContext is used.
 */
class TenantTeamResolver implements PermissionsTeamResolver
{
    private int|string|null $override = null;

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $this->override = $id instanceof Model ? $id->getKey() : $id;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->override ?? app(TenantContext::class)->id();
    }
}
