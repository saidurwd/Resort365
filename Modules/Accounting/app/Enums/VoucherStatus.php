<?php

namespace Modules\Accounting\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum VoucherStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Posted = 'posted';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Posted => __('Posted'),
            self::Void => __('Void'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Posted => 'success',
            self::Void => 'secondary',
        };
    }
}
