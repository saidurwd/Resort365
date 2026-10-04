<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * When a dish is served (ARCHITECTURE §5.10.2): orders can hold mains until starters are done (Step 3.5).
 */
enum Course: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Starter = 'starter';
    case Main = 'main';
    case Side = 'side';
    case Dessert = 'dessert';
    case Drink = 'drink';

    public function label(): string
    {
        return match ($this) {
            self::Starter => __('Starter'),
            self::Main => __('Main'),
            self::Side => __('Side'),
            self::Dessert => __('Dessert'),
            self::Drink => __('Drink'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Starter => 'info',
            self::Main => 'primary',
            self::Side => 'secondary',
            self::Dessert => 'warning',
            self::Drink => 'success',
        };
    }
}
