<?php

namespace Modules\Procurement\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum RfqStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Draft = 'draft';
    case Sent = 'sent';
    case Awarded = 'awarded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Sent => __('Sent to vendors'),
            self::Awarded => __('Awarded'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Sent => 'info',
            self::Awarded => 'success',
            self::Cancelled => 'secondary',
        };
    }
}
