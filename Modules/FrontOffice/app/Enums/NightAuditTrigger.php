<?php

namespace Modules\FrontOffice\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Who started a night audit: a person from the wizard, or the scheduler at the audit time.
 */
enum NightAuditTrigger: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Manual = 'manual';
    case Scheduled = 'scheduled';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Manual'),
            self::Scheduled => __('Scheduled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Manual => 'primary',
            self::Scheduled => 'secondary',
        };
    }
}
