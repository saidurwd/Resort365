<?php

namespace Modules\Accounting\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum BankAccountKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Bank = 'bank';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Bank => __('Bank account'),
            self::Cash => __('Cash'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Bank => 'primary',
            self::Cash => 'secondary',
        };
    }
}
