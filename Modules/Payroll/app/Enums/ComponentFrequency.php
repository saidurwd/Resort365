<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum ComponentFrequency: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Monthly = 'monthly';
    case Annual = 'annual';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => __('Every month'),
            self::Annual => __('A few times a year'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Monthly => 'secondary',
            self::Annual => 'warning',
        };
    }
}
