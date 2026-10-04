<?php

namespace Modules\Housekeeping\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Pending → In progress → Done (the room is clean) → Inspected; a failed inspection sends it back to Pending.
 */
enum TaskStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Inspected = 'inspected';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::InProgress => __('In progress'),
            self::Done => __('Done'),
            self::Inspected => __('Inspected'),
            self::Skipped => __('Skipped'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InProgress => 'info',
            self::Done => 'success',
            self::Inspected => 'primary',
            self::Skipped => 'secondary',
        };
    }
}
