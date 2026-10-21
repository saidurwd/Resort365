<?php

namespace Modules\Platform\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;
use Carbon\CarbonImmutable;

enum BillingPeriod: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Monthly'),
            self::Yearly => __('Yearly'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Monthly => 'info',
            self::Yearly => 'primary',
        };
    }

    /**
     * The last day of a period that starts on the given day (a month from 31 January ends on 28 or 29 February).
     */
    public function periodEnd(CarbonImmutable $start): CarbonImmutable
    {
        return match ($this) {
            self::Monthly => $start->addMonthNoOverflow()->subDay(),
            self::Yearly => $start->addYearNoOverflow()->subDay(),
        };
    }
}
