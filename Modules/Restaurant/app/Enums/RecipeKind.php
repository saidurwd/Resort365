<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum RecipeKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Dish = 'dish';
    case Variant = 'variant';
    case Modifier = 'modifier';
    case Sub = 'sub';

    public function label(): string
    {
        return match ($this) {
            self::Dish => __('Menu item'),
            self::Variant => __('Variant'),
            self::Modifier => __('Modifier'),
            self::Sub => __('Sub-recipe'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Dish => 'primary',
            self::Variant => 'info',
            self::Modifier => 'secondary',
            self::Sub => 'warning',
        };
    }
}
