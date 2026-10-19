<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum CalcType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Fixed = 'fixed';
    case Percent = 'percent';
    case Formula = 'formula';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('Fixed amount'),
            self::Percent => __('Percent of another component'),
            self::Formula => __('Formula'),
            self::Employee => __('Set for each employee'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Fixed => 'secondary',
            self::Percent => 'secondary',
            self::Formula => 'secondary',
            self::Employee => 'secondary',
        };
    }
}
