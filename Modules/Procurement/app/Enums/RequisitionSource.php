<?php

namespace Modules\Procurement\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum RequisitionSource: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Manual = 'manual';
    case Reorder = 'reorder';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Entered by hand'),
            self::Reorder => __('From reorder levels'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Manual => 'secondary',
            self::Reorder => 'info',
        };
    }
}
