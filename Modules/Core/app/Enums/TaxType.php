<?php

namespace Modules\Core\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum TaxType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => __('Percentage'),
            self::Fixed => __('Fixed amount per unit'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Percent => 'primary',
            self::Fixed => 'info',
        };
    }
}
