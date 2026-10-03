<?php

namespace Modules\Property\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum AmenityCategory: string implements HasLabelAndColor
{
    use EnumHelpers;

    case InRoom = 'in_room';
    case Bathroom = 'bathroom';
    case Outdoor = 'outdoor';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::InRoom => __('In-room'),
            self::Bathroom => __('Bathroom'),
            self::Outdoor => __('Outdoor'),
            self::Service => __('Service'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InRoom => 'primary',
            self::Bathroom => 'info',
            self::Outdoor => 'success',
            self::Service => 'warning',
        };
    }
}
