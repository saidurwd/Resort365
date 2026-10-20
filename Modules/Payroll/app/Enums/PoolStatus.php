<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A pool's life (Step 6.6): worked out, finalised for payroll, then distributed when the payroll run is approved.
 */
enum PoolStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Calculated = 'calculated';
    case Finalised = 'finalised';
    case Distributed = 'distributed';

    public function label(): string
    {
        return match ($this) {
            self::Calculated => __('Worked out'),
            self::Finalised => __('Ready for payroll'),
            self::Distributed => __('Distributed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Calculated => 'secondary',
            self::Finalised => 'warning',
            self::Distributed => 'success',
        };
    }
}
