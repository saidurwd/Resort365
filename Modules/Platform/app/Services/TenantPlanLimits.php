<?php

namespace Modules\Platform\Services;

use App\Models\Tenant;
use App\Support\Tenancy\PlanLimitReached;
use App\Support\Tenancy\TenantContext;
use Modules\Core\Contracts\PlanLimits;
use Modules\Platform\Models\Plan;

/**
 * `PlanLimits` from the current tenant's plan (Step 7.3). A tenant with no plan has no limits.
 */
class TenantPlanLimits implements PlanLimits
{
    /** @var array<int, Plan|null> */
    private array $plans = [];

    public function __construct(private readonly TenantContext $tenant) {}

    public function limit(string $name): int|float|null
    {
        return $this->plan()?->limit($name);
    }

    public function ensureWithin(string $name, int|float $newTotal): void
    {
        $plan = $this->plan();
        $max = $plan?->limit($name);

        if (! $plan instanceof Plan || $max === null || $newTotal <= $max) {
            return;
        }

        throw new PlanLimitReached($name, $max, $this->message($name, $plan->name, $max));
    }

    public function hasFeature(string $feature): bool
    {
        $plan = $this->plan();

        return ! $plan instanceof Plan || match ($feature) {
            'api_access' => $plan->api_access,
            default => false,
        };
    }

    public function planName(): ?string
    {
        return $this->plan()?->name;
    }

    /** Forget what was read (after a plan changes in the same process). */
    public function flush(): void
    {
        $this->plans = [];
    }

    private function plan(): ?Plan
    {
        $tenant = $this->tenant->tenant();

        if (! $tenant instanceof Tenant || $tenant->plan_id === null) {
            return null;
        }

        return $this->plans[$tenant->id] ??= Plan::query()->find($tenant->plan_id);
    }

    private function message(string $name, string $plan, int|float $max): string
    {
        $number = number_format((float) $max, $name === 'storage_gb' ? 0 : 0);

        return match ($name) {
            'max_properties' => __('Property limit reached: the :plan plan includes :count :things. Upgrade your plan to add more.', ['plan' => $plan, 'count' => $number, 'things' => $max === 1 ? __('property') : __('properties')]),
            'max_rooms' => __('Room limit reached: the :plan plan includes :count :things. Upgrade your plan to add more.', ['plan' => $plan, 'count' => $number, 'things' => $max === 1 ? __('room') : __('rooms')]),
            'max_users' => __('User limit reached: the :plan plan includes :count :things. Deactivate someone or upgrade your plan.', ['plan' => $plan, 'count' => $number, 'things' => $max === 1 ? __('user') : __('users')]),
            'storage_gb' => __('Storage limit reached: the :plan plan includes :count GB of files. Delete some files or upgrade your plan.', ['plan' => $plan, 'count' => $number]),
            default => __('The :plan plan does not allow more.', ['plan' => $plan]),
        };
    }
}
