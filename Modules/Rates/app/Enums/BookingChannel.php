<?php

namespace Modules\Rates\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Where a rate plan can be sold.
 */
enum BookingChannel: string implements HasLabelAndColor
{
    use EnumHelpers;

    case FrontDesk = 'front_desk';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::FrontDesk => __('Front desk'),
            self::Online => __('Online booking'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FrontDesk => 'secondary',
            self::Online => 'info',
        };
    }
}
