<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Where a room-service or location-delivery order is (ARCHITECTURE §5.10.4): ordered → preparing →
 * out for delivery → delivered.
 */
enum DeliveryStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Ordered = 'ordered';
    case Preparing = 'preparing';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Ordered => __('Ordered'),
            self::Preparing => __('Preparing'),
            self::OutForDelivery => __('Out for delivery'),
            self::Delivered => __('Delivered'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ordered => 'warning',
            self::Preparing => 'primary',
            self::OutForDelivery => 'info',
            self::Delivered => 'success',
        };
    }
}
