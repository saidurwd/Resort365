<?php

namespace Modules\Website\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * The colour theme of a property's public website (Step 8.2a): one of a few designed palettes (resources/scss/website.scss,
 * `.theme-<value>`), so no colour is ever typed in.
 */
enum SiteTheme: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Forest = 'forest';
    case Ocean = 'ocean';
    case Sunset = 'sunset';
    case Sand = 'sand';
    case Slate = 'slate';
    case Berry = 'berry';

    public function label(): string
    {
        return match ($this) {
            self::Forest => __('Forest green'),
            self::Ocean => __('Ocean blue'),
            self::Sunset => __('Sunset orange'),
            self::Sand => __('Sand and gold'),
            self::Slate => __('Slate'),
            self::Berry => __('Berry'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Forest => 'success',
            self::Ocean => 'primary',
            self::Sunset, self::Sand => 'warning',
            self::Slate => 'secondary',
            self::Berry => 'danger',
        };
    }
}
