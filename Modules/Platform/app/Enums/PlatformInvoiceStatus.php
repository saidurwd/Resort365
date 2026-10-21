<?php

namespace Modules\Platform\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum PlatformInvoiceStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Paid => __('Paid'),
            self::Void => __('Void'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Paid => 'success',
            self::Void => 'secondary',
        };
    }
}
