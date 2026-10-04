<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * An invoice is never changed once issued; credit notes reduce it.
 */
enum InvoiceStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Issued = 'issued';
    case PartiallyCredited = 'partially_credited';
    case Credited = 'credited';

    public function label(): string
    {
        return match ($this) {
            self::Issued => __('Issued'),
            self::PartiallyCredited => __('Partly credited'),
            self::Credited => __('Credited'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Issued => 'success',
            self::PartiallyCredited => 'warning',
            self::Credited => 'secondary',
        };
    }
}
