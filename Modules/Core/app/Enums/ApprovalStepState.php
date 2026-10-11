<?php

namespace Modules\Core\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A step of the chain as the approval panel shows it (Step 5.1).
 */
enum ApprovalStepState: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Waiting = 'waiting';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => __('Not yet'),
            self::Pending => __('Awaiting decision'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Waiting => 'secondary',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
