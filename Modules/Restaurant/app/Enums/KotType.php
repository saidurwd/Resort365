<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A kitchen ticket for new items, or one telling the station items were voided.
 */
enum KotType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case New = 'new';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Void => __('Void'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'primary',
            self::Void => 'danger',
        };
    }
}
