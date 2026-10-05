<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How an order is printed as bills (ARCHITECTURE §5.10.7): one bill, or split by item, by seat,
 * equally between a number of people, or by amounts.
 */
enum SplitMode: string implements HasLabelAndColor
{
    use EnumHelpers;

    case None = 'none';
    case Item = 'item';
    case Seat = 'seat';
    case Equal = 'equal';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::None => __('One bill'),
            self::Item => __('By item'),
            self::Seat => __('By seat'),
            self::Equal => __('Equally'),
            self::Amount => __('By amount'),
        };
    }

    public function color(): string
    {
        return 'secondary';
    }
}
