<?php

namespace Modules\Property\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A room's cleaning state. Shown read-only here; Housekeeping changes it (Phase 4).
 */
enum HousekeepingStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Clean = 'clean';
    case Dirty = 'dirty';
    case Inspected = 'inspected';

    public function label(): string
    {
        return match ($this) {
            self::Clean => __('Clean'),
            self::Dirty => __('Dirty'),
            self::Inspected => __('Inspected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Clean => 'success',
            self::Dirty => 'danger',
            self::Inspected => 'primary',
        };
    }
}
