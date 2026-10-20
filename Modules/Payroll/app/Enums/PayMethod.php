<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum PayMethod: string implements HasLabelAndColor
{
    use EnumHelpers;

    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => __('Bank transfer'),
            self::Cash => __('Cash'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BankTransfer => 'secondary',
            self::Cash => 'secondary',
        };
    }
}
