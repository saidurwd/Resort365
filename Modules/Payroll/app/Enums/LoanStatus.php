<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum LoanStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Being repaid'),
            self::Closed => __('Repaid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Closed => 'success',
        };
    }
}
