<?php

namespace Modules\Guest\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum VipLevel: string implements HasLabelAndColor
{
    use EnumHelpers;

    case None = 'none';
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';

    public function label(): string
    {
        return match ($this) {
            self::None => __('Regular'),
            self::Silver => __('VIP Silver'),
            self::Gold => __('VIP Gold'),
            self::Platinum => __('VIP Platinum'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'secondary',
            self::Silver => 'info',
            self::Gold => 'warning',
            self::Platinum => 'primary',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Silver => 1,
            self::Gold => 2,
            self::Platinum => 3,
        };
    }
}
