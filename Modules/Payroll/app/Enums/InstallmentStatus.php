<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum InstallmentStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Scheduled = 'scheduled';
    case Deducted = 'deducted';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('To be deducted'),
            self::Deducted => __('Deducted'),
            self::Waived => __('Written off'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'secondary',
            self::Deducted => 'success',
            self::Waived => 'warning',
        };
    }
}
