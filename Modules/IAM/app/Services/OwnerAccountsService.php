<?php

namespace Modules\IAM\Services;

use App\Support\Tenancy\TenantCache;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\IAM\Actions\CreateTenantOwner;
use Modules\IAM\Contracts\OwnerAccounts;

/**
 * The first owner of a company that signed up by itself (Step 7.4). The welcome link's token is kept hashed in the tenant's cache and
 * taken out when it is used, so it works once.
 */
class OwnerAccountsService implements OwnerAccounts
{
    public const int WELCOME_LINK_MINUTES = 10;

    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantCache $cache,
    ) {}

    public function createOwner(string $name, string $email, string $password): int
    {
        return CreateTenantOwner::make()->handle($name, $email, $password)->id;
    }

    public function welcomeLink(int $userId): string
    {
        $token = Str::random(48);
        $this->cache->put($this->cacheKey($token), $userId, now()->addMinutes(self::WELCOME_LINK_MINUTES));

        return $this->context->tenantOrFail(self::class)->url('/iam/welcome/'.$token);
    }

    /**
     * The user the token was made for, once; null when it is unknown, used or expired.
     */
    public function redeem(string $token): ?int
    {
        $userId = Cache::pull($this->cache->key($this->cacheKey($token)));

        return is_int($userId) ? $userId : null;
    }

    private function cacheKey(string $token): string
    {
        return 'iam:welcome:'.hash('sha256', $token);
    }
}
