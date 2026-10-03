<?php

namespace Modules\Rates\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Badge colour of a season on the rate grid (readable in light and dark mode).
 */
enum SeasonColor: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Primary = 'primary';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Info = 'info';
    case Secondary = 'secondary';

    public function label(): string
    {
        return match ($this) {
            self::Primary => __('Teal'),
            self::Success => __('Green'),
            self::Warning => __('Amber'),
            self::Danger => __('Red'),
            self::Info => __('Blue'),
            self::Secondary => __('Grey'),
        };
    }

    public function color(): string
    {
        return $this->value;
    }
}
