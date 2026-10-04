<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Who pays a folio.
 */
enum BillTo: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Guest = 'guest';
    case Company = 'company';
    case TravelAgent = 'travel_agent';

    public function label(): string
    {
        return match ($this) {
            self::Guest => __('Guest'),
            self::Company => __('Company'),
            self::TravelAgent => __('Travel agent'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Guest => 'primary',
            self::Company => 'info',
            self::TravelAgent => 'secondary',
        };
    }
}
