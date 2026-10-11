<?php

namespace Modules\Inventory\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What a unit measures. Units of one kind convert to each other by their factors; a pack or container unit (box, sack,
 * carton) has no fixed size and is converted per item.
 */
enum UnitKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Count = 'count';
    case Weight = 'weight';
    case Volume = 'volume';
    case Length = 'length';
    case Pack = 'pack';

    public function label(): string
    {
        return match ($this) {
            self::Count => __('Count'),
            self::Weight => __('Weight'),
            self::Volume => __('Volume'),
            self::Length => __('Length'),
            self::Pack => __('Pack or container'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Count => 'secondary',
            self::Weight => 'primary',
            self::Volume => 'info',
            self::Length => 'success',
            self::Pack => 'warning',
        };
    }
}
