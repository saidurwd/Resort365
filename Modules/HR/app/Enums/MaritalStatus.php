<?php

namespace Modules\HR\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum MaritalStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function label(): string
    {
        return match ($this) {
            self::Single => __('Single'),
            self::Married => __('Married'),
            self::Divorced => __('Divorced'),
            self::Widowed => __('Widowed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Single => 'secondary',
            self::Married => 'secondary',
            self::Divorced => 'secondary',
            self::Widowed => 'secondary',
        };
    }
}
