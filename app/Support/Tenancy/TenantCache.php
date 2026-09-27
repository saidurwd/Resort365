<?php

namespace App\Support\Tenancy;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;

/**
 * Cache access with keys prefixed `t:{tenant_id}:` (ARCHITECTURE §4.2), so one
 * tenant can never read or overwrite another tenant's entries.
 */
class TenantCache
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly Repository $cache,
    ) {}

    public function key(string $key): string
    {
        return 't:'.$this->context->tenantOrFail(self::class)->id.':'.$key;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->key($key), $default);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->key($key));
    }

    public function put(string $key, mixed $value, DateTimeInterface|DateInterval|int|null $ttl = null): bool
    {
        return $this->cache->put($this->key($key), $value, $ttl);
    }

    /**
     * @template TCacheValue
     *
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember(string $key, DateTimeInterface|DateInterval|int|null $ttl, Closure $callback): mixed
    {
        return $this->cache->remember($this->key($key), $ttl, $callback);
    }

    public function forget(string $key): bool
    {
        return $this->cache->forget($this->key($key));
    }
}
