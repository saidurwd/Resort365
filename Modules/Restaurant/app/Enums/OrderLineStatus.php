<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * An order line's state (ARCHITECTURE §5.10.5): pending until sent to the kitchen; preparing, ready and served from the kitchen display (Step 3.5); or voided.
 */
enum OrderLineStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Pending = 'pending';
    case Sent = 'sent';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Not sent'),
            self::Sent => __('Sent'),
            self::Preparing => __('Preparing'),
            self::Ready => __('Ready'),
            self::Served => __('Served'),
            self::Voided => __('Voided'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'info',
            self::Preparing => 'primary',
            self::Ready => 'success',
            self::Served => 'secondary',
            self::Voided => 'danger',
        };
    }
}
