<?php

namespace Modules\Procurement\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum VendorStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'secondary',
        };
    }
}
