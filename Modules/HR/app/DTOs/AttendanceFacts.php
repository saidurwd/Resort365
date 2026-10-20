<?php

namespace Modules\HR\DTOs;

use App\Support\DTOs\Data;

/**
 * An employee's month of attendance as Payroll needs it (Step 6.5): rostered working days (holidays and leave excluded),
 * days absent (no record, marked absent, or half a day for a half-day mark), days of approved unpaid leave, and overtime.
 * Days are decimal strings with one decimal.
 */
final readonly class AttendanceFacts extends Data
{
    public function __construct(
        public int $scheduledDays,
        public string $absentDays,
        public string $unpaidLeaveDays,
        public int $overtimeMinutes,
    ) {}
}
