<?php

namespace Modules\Core\Contracts;

use Closure;

/**
 * What a tenant uses, counted by the modules that know (Step 7.3): a module registers a meter by name, with a label, a unit and a
 * closure that answers the amount for the current tenant. Platform reads them for the console and for plan limits, so it never
 * reads another module's tables. Names used by the limits: `properties`, `rooms`, `users`, and `storage` (in gigabytes).
 */
interface UsageMeters
{
    /**
     * @param  Closure(): (int|float)  $measure  the amount for the current tenant
     */
    public function register(string $name, string $label, string $unit, Closure $measure): void;

    /**
     * Every meter's name and label.
     *
     * @return array<string, array{label: string, unit: string}>
     */
    public function all(): array;

    /**
     * The amount used by the current tenant.
     */
    public function measure(string $name): int|float;
}
