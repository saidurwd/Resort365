<?php

namespace Modules\Platform\Services;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Modules\Core\Contracts\UsageMeters;
use Modules\Platform\Models\Plan;

/**
 * What a tenant uses against what its plan allows (Step 7.3), for the console. The modules count their own things through Core's
 * usage meters; this runs them inside the tenant.
 */
class UsageCollector
{
    /** Which plan limit each meter is measured against. */
    private const array LIMITS = ['properties' => 'max_properties', 'rooms' => 'max_rooms', 'users' => 'max_users', 'storage' => 'storage_gb'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly UsageMeters $meters,
    ) {}

    /**
     * @return array<string, array{label: string, unit: string, used: int|float, limit: int|float|null, percent: int|null}>
     */
    public function forTenant(Tenant $tenant, ?Plan $plan): array
    {
        return $this->context->run($tenant, function () use ($plan): array {
            $usage = [];

            foreach ($this->meters->all() as $name => $meter) {
                $used = $this->meters->measure($name);
                $limit = isset(self::LIMITS[$name]) ? $plan?->limit(self::LIMITS[$name]) : null;
                $usage[$name] = ['label' => __($meter['label']), 'unit' => $meter['unit'], 'used' => $used, 'limit' => $limit, 'percent' => $limit !== null && $limit > 0 ? (int) min(100, round($used * 100 / $limit)) : null];
            }

            return $usage;
        });
    }

    /**
     * What is over a plan's limits, as sentences, for a tenant that moves to it.
     *
     * @return list<string>
     */
    public function overLimits(Tenant $tenant, Plan $plan): array
    {
        $over = [];

        foreach ($this->forTenant($tenant, $plan) as $meter) {
            if ($meter['limit'] !== null && $meter['used'] > $meter['limit']) {
                $over[] = __(':label: :used in use, the plan allows :limit. Nothing is removed, but no more can be added.', ['label' => $meter['label'], 'used' => number_format((float) $meter['used'], $meter['unit'] === 'GB' ? 2 : 0), 'limit' => number_format((float) $meter['limit'], 0)]);
            }
        }

        return $over;
    }
}
