<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * The menu engineering classes (Step 7.2): a dish is popular or not, and earns a high or low margin per portion.
 */
enum MenuClass: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Star = 'star';
    case Plowhorse = 'plowhorse';
    case Puzzle = 'puzzle';
    case Dog = 'dog';

    public function label(): string
    {
        return match ($this) {
            self::Star => __('Star'),
            self::Plowhorse => __('Plowhorse'),
            self::Puzzle => __('Puzzle'),
            self::Dog => __('Dog'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Star => 'success',
            self::Plowhorse => 'warning',
            self::Puzzle => 'info',
            self::Dog => 'danger',
        };
    }

    /** What to do with a dish of this class. */
    public function advice(): string
    {
        return match ($this) {
            self::Star => __('Popular and profitable: keep it, feature it and hold the quality.'),
            self::Plowhorse => __('Popular but earns little: raise the price a little or lower the portion cost.'),
            self::Puzzle => __('Profitable but slow: promote it, move it up the menu or rename it.'),
            self::Dog => __('Neither popular nor profitable: rework it, or take it off the menu.'),
        };
    }
}
