<?php

namespace Modules\HR\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum EmploymentType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Permanent = 'permanent';
    case Contract = 'contract';
    case Seasonal = 'seasonal';
    case Intern = 'intern';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => __('Permanent'),
            self::Contract => __('Contract'),
            self::Seasonal => __('Seasonal'),
            self::Intern => __('Intern'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Permanent => 'success',
            self::Contract => 'info',
            self::Seasonal => 'warning',
            self::Intern => 'secondary',
        };
    }
}
