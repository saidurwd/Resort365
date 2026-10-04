<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A dish (a recipe from Phase 5), a direct-stock item sold as bought (bottled drinks), an open item priced on the POS, or a combo of other items.
 */
enum MenuItemKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Dish = 'dish';
    case DirectStock = 'direct_stock';
    case Open = 'open';
    case Combo = 'combo';

    public function label(): string
    {
        return match ($this) {
            self::Dish => __('Dish'),
            self::DirectStock => __('Direct stock'),
            self::Open => __('Open item'),
            self::Combo => __('Combo / set menu'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Dish => 'primary',
            self::DirectStock => 'info',
            self::Open => 'warning',
            self::Combo => 'success',
        };
    }
}
