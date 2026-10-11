<?php

namespace Modules\Inventory\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum TransferStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Dispatched = 'dispatched';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Dispatched => __('In transit'),
            self::Received => __('Received'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Dispatched => 'warning',
            self::Received => 'success',
            self::Cancelled => 'secondary',
        };
    }
}
