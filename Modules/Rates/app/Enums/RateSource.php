<?php

namespace Modules\Rates\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Where a night's price came from (NightlyRateResolver).
 */
enum RateSource: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Override = 'override';
    case Season = 'season';
    case Base = 'base';

    public function label(): string
    {
        return match ($this) {
            self::Override => __('Date price'),
            self::Season => __('Season rate'),
            self::Base => __('Base rate'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Override => 'danger',
            self::Season => 'primary',
            self::Base => 'secondary',
        };
    }
}
