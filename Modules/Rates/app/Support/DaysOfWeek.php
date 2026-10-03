<?php

namespace Modules\Rates\Support;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Days-of-week bitmask (rates.dow_mask): bit 1 = Monday, 2 = Tuesday … 64 = Sunday (ISO order).
 */
final class DaysOfWeek
{
    public const int EVERY_DAY = 127;

    /**
     * @param  list<int>  $isoDays  1 = Monday … 7 = Sunday
     */
    public static function mask(array $isoDays): int
    {
        $mask = 0;

        foreach ($isoDays as $day) {
            if ($day < 1 || $day > 7) {
                throw new InvalidArgumentException("Day {$day} is not an ISO weekday (1–7).");
            }

            $mask |= 1 << ($day - 1);
        }

        return $mask;
    }

    /**
     * @return list<int>
     */
    public static function days(int $mask): array
    {
        return array_values(array_filter(range(1, 7), fn (int $day): bool => ($mask & (1 << ($day - 1))) !== 0));
    }

    public static function includes(int $mask, CarbonInterface $date): bool
    {
        return ($mask & (1 << ($date->dayOfWeekIso - 1))) !== 0;
    }

    /**
     * Number of days in the mask (fewer days = more specific).
     */
    public static function count(int $mask): int
    {
        return count(self::days($mask));
    }

    /**
     * ISO days from a setting such as "5,6".
     *
     * @return list<int>
     */
    public static function parse(string $days): array
    {
        $parsed = array_map(intval(...), array_filter(array_map(trim(...), explode(',', $days)), fn (string $day): bool => $day !== ''));

        return array_values(array_unique(array_filter($parsed, fn (int $day): bool => $day >= 1 && $day <= 7)));
    }

    /**
     * Short names, e.g. "Fri, Sat"; "Every day" for 127.
     */
    public static function label(int $mask): string
    {
        if ($mask === self::EVERY_DAY) {
            return __('Every day');
        }

        $names = [1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat'), 7 => __('Sun')];

        return implode(', ', array_map(fn (int $day): string => $names[$day], self::days($mask)));
    }
}
