<?php

namespace Tests\Fixtures;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum RoomStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Clean = 'clean';
    case Dirty = 'dirty';

    public function label(): string
    {
        return match ($this) {
            self::Clean => 'Clean',
            self::Dirty => 'Dirty',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Clean => 'success',
            self::Dirty => 'warning',
        };
    }
}
