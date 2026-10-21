<?php

namespace Modules\Platform\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum SubscriptionStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Trialing = 'trialing';
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => __('Trial'),
            self::Active => __('Active'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Trialing => 'info',
            self::Active => 'success',
            self::Cancelled => 'secondary',
        };
    }
}
