<?php

namespace Modules\HR\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum EmployeeStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case OnProbation = 'on_probation';
    case Active = 'active';
    case Exited = 'exited';

    public function label(): string
    {
        return match ($this) {
            self::OnProbation => __('On probation'),
            self::Active => __('Active'),
            self::Exited => __('Exited'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OnProbation => 'warning',
            self::Active => 'success',
            self::Exited => 'secondary',
        };
    }
}
