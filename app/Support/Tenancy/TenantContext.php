<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Context;

/**
 * The tenant the current request, job or command is acting for.
 *
 * Bound as a scoped instance (reset per request and per queued job). Setting a
 * tenant also records its id in Laravel's hidden Context, which travels with
 * queued jobs so TenantAware jobs can restore it (see RestoreTenantContext).
 */
class TenantContext
{
    public const string CONTEXT_KEY = 'tenant_id';

    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;

        Context::addHidden(self::CONTEXT_KEY, $tenant->id);
        Context::add('tenant', $tenant->slug);
    }

    public function forget(): void
    {
        $this->tenant = null;

        Context::forgetHidden(self::CONTEXT_KEY);
        Context::forget('tenant');
    }

    public function check(): bool
    {
        return $this->tenant instanceof Tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    /**
     * The current tenant, or an exception when none is set.
     */
    public function tenantOrFail(string $for = 'tenant-owned data'): Tenant
    {
        return $this->tenant ?? throw TenantContextMissing::forService($for);
    }

    /**
     * Run the callback as the given tenant, then restore the previous tenant (or none).
     *
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            $previous instanceof Tenant ? $this->set($previous) : $this->forget();
        }
    }
}
