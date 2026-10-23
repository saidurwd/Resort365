<?php

namespace Modules\Core\Contracts;

use App\Support\Tenancy\PlanLimitReached;

/**
 * What the current tenant's plan allows (ARCHITECTURE §5.2, Step 7.3). A module that adds a property, a room, a user or a file asks
 * before it does, with the total it would have afterwards. Platform implements this from the tenant's plan; a tenant with no plan
 * (and an installation without Platform) has no limits.
 *
 * Limits: `max_properties`, `max_rooms`, `max_users` (counts) and `storage_gb` (gigabytes).
 */
interface PlanLimits
{
    /**
     * The most the plan allows, or null for no limit.
     */
    public function limit(string $name): int|float|null;

    /**
     * Says no, with a message that points to an upgrade, when `$newTotal` would be more than the plan allows.
     *
     * @throws PlanLimitReached
     */
    public function ensureWithin(string $name, int|float $newTotal): void;

    /**
     * Whether the plan includes a feature: `api_access` (the REST API, Step 8.1). A tenant with no plan has every feature.
     */
    public function hasFeature(string $feature): bool;

    /**
     * The name of the tenant's plan, or null without one.
     */
    public function planName(): ?string;
}
