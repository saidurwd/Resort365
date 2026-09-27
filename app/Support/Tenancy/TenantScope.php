<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query on a tenant-owned model to the current tenant. Fails closed:
 * without a current tenant the query throws instead of returning every tenant's rows.
 *
 * @implements Scope<Model>
 */
class TenantScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id() ?? throw TenantContextMissing::forModel($model::class);

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
