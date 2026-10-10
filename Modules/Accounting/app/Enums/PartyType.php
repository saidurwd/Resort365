<?php

namespace Modules\Accounting\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Who a journal line is about (the party dimension, ARCHITECTURE §5.14): a guest, company, travel agent,
 * vendor or employee, by id in their own module.
 */
enum PartyType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Guest = 'guest';
    case Company = 'company';
    case TravelAgent = 'travel_agent';
    case Vendor = 'vendor';
    case Employee = 'employee';

    public function label(): string
    {
        return match ($this) {
            self::Guest => __('Guest'),
            self::Company => __('Company'),
            self::TravelAgent => __('Travel agent'),
            self::Vendor => __('Vendor'),
            self::Employee => __('Employee'),
        };
    }

    public function color(): string
    {
        return 'secondary';
    }
}
