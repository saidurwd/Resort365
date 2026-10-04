<?php

namespace Modules\FrontOffice\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Running while the audit works; Completed once the business date moved on; Blocked when a check
 * stopped it (e.g. departures still in house); Failed on an error. Blocked and failed audits can
 * be run again.
 */
enum NightAuditStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Running = 'running';
    case Completed = 'completed';
    case Blocked = 'blocked';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => __('Running'),
            self::Completed => __('Completed'),
            self::Blocked => __('Blocked'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'info',
            self::Completed => 'success',
            self::Blocked => 'warning',
            self::Failed => 'danger',
        };
    }

    public function canRetry(): bool
    {
        return in_array($this, [self::Blocked, self::Failed], true);
    }
}
