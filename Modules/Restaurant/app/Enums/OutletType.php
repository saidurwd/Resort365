<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What kind of outlet: it decides defaults on the POS (tables for restaurants, rooms for room service).
 */
enum OutletType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Restaurant = 'restaurant';
    case Bar = 'bar';
    case Cafe = 'cafe';
    case RoomService = 'room_service';
    case Minibar = 'minibar';

    public function label(): string
    {
        return match ($this) {
            self::Restaurant => __('Restaurant'),
            self::Bar => __('Bar'),
            self::Cafe => __('Café'),
            self::RoomService => __('Room service'),
            self::Minibar => __('Mini-bar'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Restaurant => 'primary',
            self::Bar => 'info',
            self::Cafe => 'success',
            self::RoomService => 'warning',
            self::Minibar => 'secondary',
        };
    }
}
