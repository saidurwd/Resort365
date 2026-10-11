<?php

namespace Modules\Core\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What was done on an approval request (Step 5.1), as the action log records it.
 */
enum ApprovalActionType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => __('Asked'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Cancelled => __('Withdrawn'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'secondary',
        };
    }
}
