<?php

namespace App\Support\UiKit;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Example status enum for the UI kit only.
 */
enum DemoStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Tentative = 'tentative';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Tentative => __('Tentative'),
            self::Confirmed => __('Confirmed'),
            self::CheckedIn => __('Checked in'),
            self::CheckedOut => __('Checked out'),
            self::Cancelled => __('Cancelled'),
            self::NoShow => __('No-show'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Tentative => 'warning',
            self::Confirmed => 'success',
            self::CheckedIn => 'primary',
            self::CheckedOut => 'secondary',
            self::Cancelled => 'danger',
            self::NoShow => 'info',
        };
    }
}
