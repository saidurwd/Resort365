<?php

namespace Modules\Core\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum SequenceReset: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Never = 'never';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Never => __('Never'),
            self::Yearly => __('Every year'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Never => 'secondary',
            self::Yearly => 'info',
        };
    }
}
