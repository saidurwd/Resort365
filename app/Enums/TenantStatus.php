<?php

namespace App\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum TenantStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Trial = 'trial';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trial => __('Trial'),
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Trial => 'info',
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Cancelled => 'secondary',
        };
    }

    /**
     * Whether the tenant's users may use the application.
     */
    public function canAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }
}
