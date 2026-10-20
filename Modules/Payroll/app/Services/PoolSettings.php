<?php

namespace Modules\Payroll\Services;

use Modules\Core\Contracts\Settings;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolRule;

/**
 * The tenant's pool settings (Step 6.6): the share of what is collected that goes to staff, and the usual rule.
 */
class PoolSettings
{
    public function __construct(private readonly Settings $settings) {}

    /** Percent of the collected amount shared with staff (0 to 100). */
    public function staffPercent(PoolKind $kind, ?int $propertyId): string
    {
        $key = $kind === PoolKind::Tips ? 'payroll.tips_staff_percent' : 'payroll.service_charge_staff_percent';

        return (string) $this->settings->get($key, $propertyId);
    }

    public function rule(?int $propertyId): PoolRule
    {
        return PoolRule::tryFrom((string) $this->settings->get('payroll.pool_rule', $propertyId)) ?? PoolRule::Points;
    }
}
