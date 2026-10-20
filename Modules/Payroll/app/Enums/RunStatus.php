<?php

namespace Modules\Payroll\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum RunStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Draft = 'draft';
    case Calculated = 'calculated';
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Calculated => __('Calculated'),
            self::Pending => __('Waiting for approval'),
            self::Approved => __('Approved'),
            self::Paid => __('Paid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Calculated => 'info',
            self::Pending => 'warning',
            self::Approved => 'primary',
            self::Paid => 'success',
        };
    }
}
