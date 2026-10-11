<?php

namespace Modules\Inventory\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Why stock was adjusted by hand (Step 5.3).
 */
enum AdjustmentReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case CountVariance = 'count_variance';
    case Damage = 'damage';
    case Expired = 'expired';
    case Theft = 'theft';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CountVariance => __('Count difference'),
            self::Damage => __('Damaged'),
            self::Expired => __('Expired'),
            self::Theft => __('Lost or stolen'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return 'secondary';
    }
}
