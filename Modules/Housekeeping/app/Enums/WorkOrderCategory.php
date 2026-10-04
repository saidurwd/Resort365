<?php

namespace Modules\Housekeeping\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What kind of fault a work order is about.
 */
enum WorkOrderCategory: string implements HasLabelAndColor
{
    use EnumHelpers;

    case AirConditioning = 'air_conditioning';
    case Electrical = 'electrical';
    case Plumbing = 'plumbing';
    case Furniture = 'furniture';
    case Building = 'building';
    case Grounds = 'grounds';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AirConditioning => __('Air conditioning'),
            self::Electrical => __('Electrical'),
            self::Plumbing => __('Plumbing'),
            self::Furniture => __('Furniture & fittings'),
            self::Building => __('Building'),
            self::Grounds => __('Grounds & garden'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AirConditioning => 'info',
            self::Electrical => 'warning',
            self::Plumbing => 'primary',
            self::Furniture => 'secondary',
            self::Building => 'secondary',
            self::Grounds => 'success',
            self::Other => 'secondary',
        };
    }
}
