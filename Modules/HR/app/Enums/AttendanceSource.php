<?php

namespace Modules\HR\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum AttendanceSource: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Manual = 'manual';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Entered by hand'),
            self::Import => __('Imported'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Manual => 'secondary',
            self::Import => 'info',
        };
    }
}
