<?php

namespace Modules\Property\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What a rate or booking is for: a room type or a whole cottage type (ARCHITECTURE §6.1).
 * The values are the morph aliases of RoomType and CottageType.
 */
enum UnitKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case RoomType = 'room_type';
    case CottageType = 'cottage_type';

    public function label(): string
    {
        return match ($this) {
            self::RoomType => __('Room type'),
            self::CottageType => __('Cottage type (whole)'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RoomType => 'info',
            self::CottageType => 'primary',
        };
    }
}
