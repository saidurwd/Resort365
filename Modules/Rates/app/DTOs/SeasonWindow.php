<?php

namespace Modules\Rates\DTOs;

use App\Support\DTOs\Data;

/**
 * A season with its periods, as the resolver sees it. Periods are [start, end] dates (Y-m-d, both included).
 */
final readonly class SeasonWindow extends Data
{
    /**
     * @param  list<array{0: string, 1: string}>  $periods
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $color,
        public int $priority,
        public array $periods,
    ) {}

    public function covers(string $date): bool
    {
        foreach ($this->periods as [$start, $end]) {
            if ($date >= $start && $date <= $end) {
                return true;
            }
        }

        return false;
    }
}
