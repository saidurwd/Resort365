<?php

namespace Modules\Reports\DTOs;

use App\Support\DTOs\Data;

/**
 * What a report is asked about (Step 7.1): the properties (more than one is a consolidated report; they share one currency),
 * a date range, and for some reports whether bookings are counted by booking date or arrival and how many top rows to show.
 */
final readonly class ReportFilter extends Data
{
    /**
     * @param  list<int>  $propertyIds
     * @param  string  $by  "booked" or "arrival"
     */
    public function __construct(
        public array $propertyIds,
        public string $from,
        public string $to,
        public string $by = 'booked',
        public int $limit = 10,
        public string $currency = '',
    ) {}

    public function consolidated(): bool
    {
        return count($this->propertyIds) > 1;
    }

    /** The number of days from the first date to the last, both counted. */
    public function days(): int
    {
        return (int) round((strtotime($this->to) - strtotime($this->from)) / 86400) + 1;
    }
}
