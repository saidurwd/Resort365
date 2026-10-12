<?php

namespace Modules\Procurement\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum PurchaseOrderStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Sent = 'sent';
    case Partial = 'partial';
    case Received = 'received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Pending => __('Waiting for approval'),
            self::Approved => __('Approved'),
            self::Sent => __('Sent to vendor'),
            self::Partial => __('Partly received'),
            self::Received => __('Received'),
            self::Closed => __('Closed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Sent => 'primary',
            self::Partial => 'warning',
            self::Received => 'success',
            self::Closed => 'secondary',
            self::Cancelled => 'danger',
        };
    }
}
