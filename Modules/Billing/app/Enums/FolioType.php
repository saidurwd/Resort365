<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A booking's folios (ARCHITECTURE §5.9): the guest's own, a company's, or a group's master folio.
 */
enum FolioType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Guest = 'guest';
    case Company = 'company';
    case Master = 'master';

    public function label(): string
    {
        return match ($this) {
            self::Guest => __('Guest folio'),
            self::Company => __('Company folio'),
            self::Master => __('Master folio'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Guest => 'primary',
            self::Company => 'info',
            self::Master => 'secondary',
        };
    }
}
