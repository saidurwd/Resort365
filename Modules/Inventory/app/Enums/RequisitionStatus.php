<?php

namespace Modules\Inventory\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum RequisitionStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for approval'),
            self::Approved => __('Approved: to issue'),
            self::Rejected => __('Rejected'),
            self::Issued => __('Issued'),
            self::Cancelled => __('Withdrawn'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Issued => 'success',
            self::Cancelled => 'secondary',
        };
    }
}
