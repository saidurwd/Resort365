<?php

namespace Modules\Payroll\Services;

use Modules\Core\Contracts\Settings;
use Modules\Payroll\DTOs\PayrollRules;

/**
 * The tenant's payroll rules from Settings (Step 6.5): overtime multiplier and hours a month, and the days a monthly salary
 * is divided by for a day's pay.
 */
class PayrollSettings
{
    public function __construct(private readonly Settings $settings) {}

    public function rules(?int $propertyId = null): PayrollRules
    {
        return new PayrollRules(
            (string) $this->settings->get('payroll.overtime_multiplier', $propertyId), (string) $this->settings->get('payroll.overtime_hours_per_month', $propertyId),
            max(1, (int) $this->settings->get('payroll.salary_days_basis', $propertyId)),
        );
    }
}
