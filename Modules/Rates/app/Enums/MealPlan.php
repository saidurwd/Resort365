<?php

namespace Modules\Rates\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Meals included in a rate plan (ARCHITECTURE §5.5).
 */
enum MealPlan: string implements HasLabelAndColor
{
    use EnumHelpers;

    case EP = 'EP';
    case CP = 'CP';
    case MAP = 'MAP';
    case AP = 'AP';

    public function label(): string
    {
        return match ($this) {
            self::EP => __('EP · Room only'),
            self::CP => __('CP · Breakfast'),
            self::MAP => __('MAP · Breakfast and dinner'),
            self::AP => __('AP · All meals'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EP => 'secondary',
            self::CP => 'info',
            self::MAP => 'primary',
            self::AP => 'success',
        };
    }

    public function includesMeals(): bool
    {
        return $this !== self::EP;
    }
}
