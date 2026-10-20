<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum LineSource: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Structure = 'structure';
    case Variable = 'variable';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Structure => __('From the structure'),
            self::Variable => __('From attendance and loans'),
            self::Adjustment => __('Adjustment'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Structure => 'secondary',
            self::Variable => 'info',
            self::Adjustment => 'warning',
        };
    }
}
