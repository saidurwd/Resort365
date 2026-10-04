<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Where a kitchen station gets its tickets: the kitchen display, a printer, or both (Q18).
 */
enum StationOutput: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Display = 'display';
    case Printer = 'printer';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Display => __('Kitchen display'),
            self::Printer => __('Printer'),
            self::Both => __('Display and printer'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Display => 'info',
            self::Printer => 'warning',
            self::Both => 'primary',
        };
    }

    public function needsPrinter(): bool
    {
        return $this !== self::Display;
    }
}
