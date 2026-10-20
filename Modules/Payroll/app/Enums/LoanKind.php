<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum LoanKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Loan = 'loan';
    case Advance = 'advance';

    public function label(): string
    {
        return match ($this) {
            self::Loan => __('Loan'),
            self::Advance => __('Salary advance'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Loan => 'info',
            self::Advance => 'warning',
        };
    }
}
