<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What a pool collects (Step 6.6): the service charge on room and restaurant bills, or the tips guests leave at the restaurant.
 */
enum PoolKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case ServiceCharge = 'service_charge';
    case Tips = 'tips';

    public function label(): string
    {
        return match ($this) {
            self::ServiceCharge => __('Service charge'),
            self::Tips => __('Tips'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ServiceCharge => 'primary',
            self::Tips => 'info',
        };
    }

    /** The payslip component the share is paid as. */
    public function component(): string
    {
        return match ($this) {
            self::ServiceCharge => 'SCSHARE',
            self::Tips => 'TIPSHARE',
        };
    }
}
