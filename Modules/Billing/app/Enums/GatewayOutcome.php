<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What a gateway says about a payment when asked (Step 8.3).
 */
enum GatewayOutcome: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Paid = 'paid';
    case Pending = 'pending';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Paid => __('Paid'),
            self::Pending => __('Pending'),
            self::Failed => __('Failed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Pending => 'warning',
            self::Failed => 'danger',
            self::Cancelled => 'secondary',
        };
    }
}
