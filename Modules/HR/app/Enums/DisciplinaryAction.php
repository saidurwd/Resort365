<?php

namespace Modules\HR\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum DisciplinaryAction: string implements HasLabelAndColor
{
    use EnumHelpers;

    case VerbalWarning = 'verbal_warning';
    case WrittenWarning = 'written_warning';
    case Suspension = 'suspension';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::VerbalWarning => __('Verbal warning'),
            self::WrittenWarning => __('Written warning'),
            self::Suspension => __('Suspension'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::VerbalWarning => 'warning',
            self::WrittenWarning => 'warning',
            self::Suspension => 'danger',
            self::Other => 'secondary',
        };
    }
}
