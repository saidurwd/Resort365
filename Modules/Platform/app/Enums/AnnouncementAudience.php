<?php

namespace Modules\Platform\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Who sees an announcement (Step 7.3): every tenant, the tenants on some plans, or some tenants.
 */
enum AnnouncementAudience: string implements HasLabelAndColor
{
    use EnumHelpers;

    case All = 'all';
    case Plans = 'plans';
    case Tenants = 'tenants';

    public function label(): string
    {
        return match ($this) {
            self::All => __('Every tenant'),
            self::Plans => __('Tenants on some plans'),
            self::Tenants => __('Some tenants'),
        };
    }

    public function color(): string
    {
        return 'secondary';
    }
}
