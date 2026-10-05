<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Why a line sent to the kitchen was voided (ARCHITECTURE §5.10.6).
 */
enum VoidReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case ChangedMind = 'changed_mind';
    case WrongOrder = 'wrong_order';
    case QualityIssue = 'quality_issue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ChangedMind => __('Guest changed mind'),
            self::WrongOrder => __('Wrong order'),
            self::QualityIssue => __('Quality issue'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ChangedMind => 'secondary',
            self::WrongOrder => 'warning',
            self::QualityIssue => 'danger',
            self::Other => 'secondary',
        };
    }
}
