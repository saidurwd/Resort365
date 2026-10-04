<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Open folios take postings; settled and closed ones do not (settlement comes with check-out).
 */
enum FolioStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Open = 'open';
    case Settled = 'settled';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Settled => __('Settled'),
            self::Closed => __('Closed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Settled => 'info',
            self::Closed => 'secondary',
        };
    }
}
