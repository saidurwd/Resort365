<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum ComponentKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Earning = 'earning';
    case Deduction = 'deduction';
    case Employer = 'employer';

    public function label(): string
    {
        return match ($this) {
            self::Earning => __('Earning'),
            self::Deduction => __('Deduction'),
            self::Employer => __('Employer cost'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Earning => 'success',
            self::Deduction => 'danger',
            self::Employer => 'info',
        };
    }
}
