<?php

namespace Modules\Accounting\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A journal entry (ARCHITECTURE §7.2): a draft is edited freely; posting makes it immutable; a reversed
 * entry has been corrected by its reversal.
 */
enum JournalStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Posted => __('Posted'),
            self::Reversed => __('Reversed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Posted => 'success',
            self::Reversed => 'warning',
        };
    }
}
