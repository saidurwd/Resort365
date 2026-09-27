<?php

namespace Modules\IAM\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum UserStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    /** Invited by email; has not set a password yet. */
    case Invited = 'invited';
    case Active = 'active';
    /** Deactivated: cannot sign in, and open sessions end on the next request. */
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Invited => __('Invited'),
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Invited => 'info',
            self::Active => 'success',
            self::Inactive => 'secondary',
        };
    }
}
