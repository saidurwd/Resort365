<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A receipt printer (bills) or a kitchen order ticket printer.
 */
enum PrinterType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Receipt = 'receipt';
    case Kot = 'kot';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => __('Receipt'),
            self::Kot => __('Kitchen tickets (KOT)'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Receipt => 'primary',
            self::Kot => 'warning',
        };
    }
}
