<?php

namespace Modules\Reservation\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A rate restriction that blocks a stay (RestrictionChecker), or a rate plan that cannot sell it.
 */
enum StayRestriction: string implements HasLabelAndColor
{
    use EnumHelpers;

    case StopSell = 'stop_sell';
    case ClosedToArrival = 'closed_to_arrival';
    case ClosedToDeparture = 'closed_to_departure';
    case MinStay = 'min_stay';
    case MaxStay = 'max_stay';
    case PlanNotValid = 'plan_not_valid';
    case NoRate = 'no_rate';

    public function label(): string
    {
        return match ($this) {
            self::StopSell => __('Stop sell'),
            self::ClosedToArrival => __('Closed to arrival'),
            self::ClosedToDeparture => __('Closed to departure'),
            self::MinStay => __('Minimum stay'),
            self::MaxStay => __('Maximum stay'),
            self::PlanNotValid => __('Rate plan not valid for these dates'),
            self::NoRate => __('No rate for these dates'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::StopSell, self::NoRate => 'danger',
            self::PlanNotValid => 'secondary',
            default => 'warning',
        };
    }
}
