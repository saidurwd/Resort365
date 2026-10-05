<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A meal of a meal plan (ARCHITECTURE §5.10.9). The POS suggests the one running now
 * (restaurant.breakfast_until, restaurant.lunch_until).
 */
enum MealPeriod: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';

    public function label(): string
    {
        return match ($this) {
            self::Breakfast => __('Breakfast'),
            self::Lunch => __('Lunch'),
            self::Dinner => __('Dinner'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Breakfast => 'warning',
            self::Lunch => 'info',
            self::Dinner => 'primary',
        };
    }

    /**
     * The meal running at a local time ("HH:MM").
     */
    public static function at(string $time, string $breakfastUntil, string $lunchUntil): self
    {
        return match (true) {
            $time < $breakfastUntil => self::Breakfast,
            $time < $lunchUntil => self::Lunch,
            default => self::Dinner,
        };
    }
}
