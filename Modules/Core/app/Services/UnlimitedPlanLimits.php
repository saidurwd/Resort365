<?php

namespace Modules\Core\Services;

use Modules\Core\Contracts\PlanLimits;

/**
 * The default `PlanLimits` (Step 7.3): no plan, no limits. Platform replaces it.
 */
class UnlimitedPlanLimits implements PlanLimits
{
    public function limit(string $name): int|float|null
    {
        return null;
    }

    public function ensureWithin(string $name, int|float $newTotal): void {}

    public function hasFeature(string $feature): bool
    {
        return true;
    }

    public function planName(): ?string
    {
        return null;
    }
}
