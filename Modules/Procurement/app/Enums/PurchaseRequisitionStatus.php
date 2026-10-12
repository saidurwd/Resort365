<?php

namespace Modules\Procurement\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum PurchaseRequisitionStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Ordered = 'ordered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Waiting for approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
            self::Ordered => __('Ordered'),
            self::Cancelled => __('Withdrawn'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Ordered => 'success',
            self::Cancelled => 'secondary',
        };
    }
}
