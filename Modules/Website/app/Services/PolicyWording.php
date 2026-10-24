<?php

namespace Modules\Website\Services;

use Modules\Rates\DTOs\CancellationRuleData;
use Modules\Rates\DTOs\CancellationTerms;
use Modules\Rates\DTOs\DepositTerms;
use Modules\Rates\Enums\BalanceDueRule;
use Modules\Rates\Enums\CancellationChargeType;
use Modules\Rates\Enums\DepositType;

/**
 * Deposit and cancellation terms in plain sentences for guests (Step 8.2a, the website's Policies page), in the current language.
 * Pure: no database.
 */
final class PolicyWording
{
    /**
     * @return list<string>
     */
    public static function deposit(DepositTerms $terms, string $currency): array
    {
        $lines = [match ($terms->type) {
            DepositType::Percentage => __('A deposit of :percent% of the stay secures the booking.', ['percent' => self::number($terms->defaultPercent)]),
            DepositType::FixedAmount => __('A deposit of :amount secures the booking.', ['amount' => Prices::format((string) $terms->fixedAmount, $currency)]),
            DepositType::FirstNight => __("The first night's price is paid as a deposit."),
            DepositType::None => __('No deposit is needed.'),
        }];

        if ($terms->type !== DepositType::None && $terms->autoCancelUnpaid && $terms->dueWithinMinutes > 0) {
            $lines[] = __('Pay it within :time of booking, or the booking is released.', ['time' => self::duration($terms->dueWithinMinutes)]);
        }

        $lines[] = $terms->balanceDueRule === BalanceDueRule::DaysBeforeArrival && $terms->balanceDueDays !== null
            ? __('The balance is due :days days before arrival.', ['days' => $terms->balanceDueDays])
            : __('The balance is paid on arrival.');

        if ($terms->fullPaymentWithinHours !== null) {
            $lines[] = __('A booking made less than :hours hours before arrival is paid in full.', ['hours' => $terms->fullPaymentWithinHours]);
        }

        return $lines;
    }

    /**
     * One sentence per period before arrival, the earliest first, then the no-show charge.
     *
     * @return list<string>
     */
    public static function cancellation(CancellationTerms $terms, string $currency): array
    {
        $rules = $terms->rules;
        usort($rules, fn (CancellationRuleData $a, CancellationRuleData $b): int => $b->daysBeforeFrom <=> $a->daysBeforeFrom);

        $lines = array_map(fn (CancellationRuleData $rule): string => self::period($rule).': '.self::charge($rule->chargeType, $rule->chargeValue, $currency), $rules);

        if ($terms->noShowChargeType instanceof CancellationChargeType) {
            $lines[] = __('If you do not arrive').': '.self::charge($terms->noShowChargeType, $terms->noShowChargeValue ?? '0', $currency);
        }

        return $lines === [] ? [__('Free cancellation.')] : $lines;
    }

    private static function period(CancellationRuleData $rule): string
    {
        return match (true) {
            $rule->daysBeforeTo === null && $rule->daysBeforeFrom === 0 => __('Cancelled at any time'),
            $rule->daysBeforeTo === null => __('Cancelled :days or more days before arrival', ['days' => $rule->daysBeforeFrom]),
            $rule->daysBeforeFrom === $rule->daysBeforeTo && $rule->daysBeforeTo === 0 => __('Cancelled on the day of arrival'),
            $rule->daysBeforeFrom === 0 => __('Cancelled :days days or fewer before arrival', ['days' => $rule->daysBeforeTo]),
            default => __('Cancelled :from to :to days before arrival', ['from' => $rule->daysBeforeFrom, 'to' => $rule->daysBeforeTo]),
        };
    }

    private static function charge(CancellationChargeType $type, string $value, string $currency): string
    {
        if ((float) $value <= 0) {
            return __('free of charge');
        }

        return match ($type) {
            CancellationChargeType::PercentOfTotal => __(':percent% of the stay', ['percent' => self::number($value)]),
            CancellationChargeType::PercentOfDeposit => __(':percent% of the deposit', ['percent' => self::number($value)]),
            CancellationChargeType::Nights => trans_choice('{1} the price of one night|[2,*] the price of :count nights', (int) $value, ['count' => (int) $value]),
            CancellationChargeType::Fixed => Prices::format($value, $currency),
        };
    }

    private static function duration(int $minutes): string
    {
        return $minutes % 1440 === 0 ? trans_choice('{1} one day|[2,*] :count days', intdiv($minutes, 1440), ['count' => intdiv($minutes, 1440)])
            : ($minutes % 60 === 0 ? trans_choice('{1} one hour|[2,*] :count hours', intdiv($minutes, 60), ['count' => intdiv($minutes, 60)])
                : trans_choice('{1} one minute|[2,*] :count minutes', $minutes, ['count' => $minutes]));
    }

    private static function number(string $value): string
    {
        return Prices::number($value) ?: '0';
    }
}
