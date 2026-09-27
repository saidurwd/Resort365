<?php

namespace Modules\IAM\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum LoginEvent: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Login = 'login';
    case Logout = 'logout';
    case Failed = 'failed';
    case Lockout = 'lockout';

    public function label(): string
    {
        return match ($this) {
            self::Login => __('Signed in'),
            self::Logout => __('Signed out'),
            self::Failed => __('Failed sign-in'),
            self::Lockout => __('Locked out'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Login => 'success',
            self::Logout => 'secondary',
            self::Failed => 'warning',
            self::Lockout => 'danger',
        };
    }
}
