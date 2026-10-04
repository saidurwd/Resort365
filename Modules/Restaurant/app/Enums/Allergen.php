<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * The 14 major food allergens, shown to the kitchen in bold.
 */
enum Allergen: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Gluten = 'gluten';
    case Crustaceans = 'crustaceans';
    case Eggs = 'eggs';
    case Fish = 'fish';
    case Peanuts = 'peanuts';
    case Soybeans = 'soybeans';
    case Milk = 'milk';
    case Nuts = 'nuts';
    case Celery = 'celery';
    case Mustard = 'mustard';
    case Sesame = 'sesame';
    case Sulphites = 'sulphites';
    case Lupin = 'lupin';
    case Molluscs = 'molluscs';

    public function label(): string
    {
        return match ($this) {
            self::Gluten => __('Gluten'),
            self::Crustaceans => __('Crustaceans'),
            self::Eggs => __('Eggs'),
            self::Fish => __('Fish'),
            self::Peanuts => __('Peanuts'),
            self::Soybeans => __('Soybeans'),
            self::Milk => __('Milk'),
            self::Nuts => __('Tree nuts'),
            self::Celery => __('Celery'),
            self::Mustard => __('Mustard'),
            self::Sesame => __('Sesame'),
            self::Sulphites => __('Sulphites'),
            self::Lupin => __('Lupin'),
            self::Molluscs => __('Molluscs'),
        };
    }

    public function color(): string
    {
        return 'warning';
    }
}
