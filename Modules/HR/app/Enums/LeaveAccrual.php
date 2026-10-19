<?php

namespace Modules\HR\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum LeaveAccrual: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Yearly = 'yearly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Yearly => __('All at the start of the year'),
            self::Monthly => __('A little each month'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Yearly => 'secondary',
            self::Monthly => 'secondary',
        };
    }
}
