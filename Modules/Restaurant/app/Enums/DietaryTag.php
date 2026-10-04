<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Dietary labels shown on the POS and menus.
 */
enum DietaryTag: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case GlutenFree = 'gluten_free';
    case Halal = 'halal';
    case Spicy = 'spicy';

    public function label(): string
    {
        return match ($this) {
            self::Vegetarian => __('Vegetarian'),
            self::Vegan => __('Vegan'),
            self::GlutenFree => __('Gluten-free'),
            self::Halal => __('Halal'),
            self::Spicy => __('Spicy'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Vegetarian => 'success',
            self::Vegan => 'success',
            self::GlutenFree => 'info',
            self::Halal => 'primary',
            self::Spicy => 'danger',
        };
    }
}
