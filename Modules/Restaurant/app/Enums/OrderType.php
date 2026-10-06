<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How an order is served (ARCHITECTURE §5.10.4). Room service, location delivery and staff meals come later.
 */
enum OrderType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case DineIn = 'dine_in';
    case Takeaway = 'takeaway';
    case RoomService = 'room_service';
    case LocationDelivery = 'location_delivery';
    case StaffMeal = 'staff_meal';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => __('Dine-in'),
            self::Takeaway => __('Takeaway'),
            self::RoomService => __('Room service'),
            self::LocationDelivery => __('Delivery'),
            self::StaffMeal => __('Staff meal'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DineIn => 'primary',
            self::Takeaway => 'info',
            self::RoomService, self::LocationDelivery => 'success',
            self::StaffMeal => 'warning',
        };
    }

    /**
     * Whether it is brought to the guest (and so has a delivery status).
     */
    public function isDelivery(): bool
    {
        return $this === self::RoomService || $this === self::LocationDelivery;
    }
}
