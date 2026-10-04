<?php

namespace Modules\Housekeeping\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Open → In progress (→ On hold) → Done, or Cancelled.
 */
enum WorkOrderStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Open = 'open';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::InProgress => __('In progress'),
            self::OnHold => __('On hold'),
            self::Done => __('Done'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::OnHold => 'secondary',
            self::Done => 'success',
            self::Cancelled => 'secondary',
        };
    }
}
