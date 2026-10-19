<?php

namespace Modules\HR\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum ExitReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Resignation = 'resignation';
    case Termination = 'termination';
    case EndOfContract = 'end_of_contract';
    case Retirement = 'retirement';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Resignation => __('Resignation'),
            self::Termination => __('Termination'),
            self::EndOfContract => __('End of contract'),
            self::Retirement => __('Retirement'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Resignation => 'info',
            self::Termination => 'danger',
            self::EndOfContract => 'secondary',
            self::Retirement => 'success',
            self::Other => 'secondary',
        };
    }
}
