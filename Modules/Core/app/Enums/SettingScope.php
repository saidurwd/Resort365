<?php

namespace Modules\Core\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Where a setting can be set: once per tenant, or per tenant with property overrides.
 */
enum SettingScope: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Tenant = 'tenant';
    case Property = 'property';

    public function label(): string
    {
        return match ($this) {
            self::Tenant => __('Company-wide'),
            self::Property => __('Per property'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Tenant => 'secondary',
            self::Property => 'info',
        };
    }
}
