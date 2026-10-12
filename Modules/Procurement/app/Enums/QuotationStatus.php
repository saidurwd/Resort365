<?php

namespace Modules\Procurement\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum QuotationStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Received = 'received';
    case Selected = 'selected';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Received => __('Received'),
            self::Selected => __('Selected'),
            self::Rejected => __('Not selected'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'info',
            self::Selected => 'success',
            self::Rejected => 'secondary',
        };
    }
}
