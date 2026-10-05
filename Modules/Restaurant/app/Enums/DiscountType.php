<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a restaurant discount is given (ARCHITECTURE §5.10.7): a percentage or an amount.
 */
enum DiscountType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Percent = 'percent';
    case Amount = 'amount';

    public function label(): string
    {
        return match ($this) {
            self::Percent => __('Percentage'),
            self::Amount => __('Amount'),
        };
    }

    public function color(): string
    {
        return 'info';
    }
}
