<?php

namespace Modules\HR\DTOs;

use App\Support\DTOs\Data;

/**
 * A shift's timing as the attendance calculator needs it (Step 6.2); times are "H:i".
 */
final readonly class ShiftTimes extends Data
{
    public function __construct(
        public string $start,
        public string $end,
        public int $breakMinutes = 0,
        public int $graceMinutes = 0,
        public int $overtimeAfterMinutes = 0,
    ) {}

    /** Minutes after midnight of "H:i". */
    public static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', substr($time, 0, 5)));

        return $hours * 60 + $minutes;
    }

    public function crossesMidnight(): bool
    {
        return self::minutes($this->end) <= self::minutes($this->start);
    }

    public function startMinutes(): int
    {
        return self::minutes($this->start);
    }

    /** Minutes after midnight of the start day; more than 1440 when the shift runs past midnight. */
    public function endMinutes(): int
    {
        return self::minutes($this->end) + ($this->crossesMidnight() ? 1440 : 0);
    }
}
