<?php

namespace Modules\Accounting\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A fiscal period (ARCHITECTURE §5.14): open to posting, closed (posting is blocked), or locked (closed
 * for good: after audit). Reopening needs accounting.period.reopen.
 */
enum PeriodStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Open = 'open';
    case Closed = 'closed';
    case Locked = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Closed => __('Closed'),
            self::Locked => __('Locked'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'warning',
            self::Locked => 'danger',
        };
    }

    public function acceptsPostings(): bool
    {
        return $this === self::Open;
    }
}
