<?php

namespace Modules\Procurement\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum VendorBillKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Purchase = 'purchase';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => __('Goods against an order'),
            self::Service => __('Service or expense'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Purchase => 'primary',
            self::Service => 'info',
        };
    }
}
