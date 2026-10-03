<?php

namespace Modules\Rates\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * What a cancellation tier charges (ARCHITECTURE §8.3 `cancellation_policy_rules`).
 */
enum CancellationChargeType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case PercentOfTotal = 'percent_of_total';
    case PercentOfDeposit = 'percent_of_deposit';
    case Nights = 'nights';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::PercentOfTotal => __('% of the stay total'),
            self::PercentOfDeposit => __('% of the deposit'),
            self::Nights => __('Number of nights'),
            self::Fixed => __('Fixed amount'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PercentOfTotal => 'danger',
            self::PercentOfDeposit => 'warning',
            self::Nights => 'info',
            self::Fixed => 'secondary',
        };
    }

    /**
     * The charge in words, e.g. "50% of the deposit", "1 night".
     */
    public function describe(string $value): string
    {
        // Drop trailing zeros of the decimals only ("50.00" → "50", but "100" stays "100").
        $number = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;

        return match ($this) {
            self::PercentOfTotal => __(':value% of the stay total', ['value' => $number]),
            self::PercentOfDeposit => __(':value% of the deposit', ['value' => $number]),
            self::Nights => trans_choice(':count night|:count nights', (int) $value),
            self::Fixed => number_format((float) $value, 2),
        };
    }
}
