<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a table is drawn on the floor plan.
 */
enum TableShape: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Square = 'square';
    case Round = 'round';
    case Rectangle = 'rectangle';

    public function label(): string
    {
        return match ($this) {
            self::Square => __('Square'),
            self::Round => __('Round'),
            self::Rectangle => __('Rectangle'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Square => 'secondary',
            self::Round => 'secondary',
            self::Rectangle => 'secondary',
        };
    }

    /**
     * Width and height on the floor-plan canvas (units of the 1000 × 600 canvas).
     *
     * @return array{int, int}
     */
    public function size(int $seats): array
    {
        return match ($this) {
            self::Round => [max(60, 30 + 10 * $seats), max(60, 30 + 10 * $seats)],
            self::Square => [70, 70],
            self::Rectangle => [max(100, 25 * $seats), 70],
        };
    }
}
