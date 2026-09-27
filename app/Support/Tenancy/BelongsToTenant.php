<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For every tenant-owned model (ARCHITECTURE §4.2, §12.1).
 *
 * - Queries are limited to the current tenant (TenantScope; throws without a tenant).
 * - `tenant_id` is filled from the current tenant on create.
 * - `tenant_id` can never change, and records of another tenant cannot be
 *   created, updated or deleted (Eloquent's save() bypasses global scopes).
 *
 * Only Platform code may use withoutGlobalScope(TenantScope::class).
 *
 * @phpstan-require-extends Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $tenantId = self::currentTenantIdFor($model);

            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $tenantId);
            }

            self::guardTenant($model, $tenantId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw new TenantMismatch('The tenant of ['.$model::class.'#'.$model->getKey().'] cannot be changed.');
            }

            self::guardTenant($model, self::currentTenantIdFor($model));
        });

        static::deleting(function (Model $model): void {
            self::guardTenant($model, self::currentTenantIdFor($model));
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    private static function currentTenantIdFor(Model $model): int
    {
        return app(TenantContext::class)->id() ?? throw TenantContextMissing::forModel($model::class);
    }

    private static function guardTenant(Model $model, int $currentTenantId): void
    {
        $recordTenantId = (int) $model->getAttribute('tenant_id');

        if ($recordTenantId !== $currentTenantId) {
            throw TenantMismatch::forModel($model::class, $model->getKey(), $recordTenantId, $currentTenantId);
        }
    }
}
