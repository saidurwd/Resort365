<?php

namespace Modules\HR\DTOs;

use App\Support\DTOs\Data;
use Modules\HR\Enums\AttendanceStatus;

/**
 * What the attendance calculator made of a day (Step 6.2).
 */
final readonly class AttendanceResult extends Data
{
    public function __construct(
        public AttendanceStatus $status,
        public int $lateMinutes = 0,
        public int $earlyLeaveMinutes = 0,
        public int $overtimeMinutes = 0,
        public int $workedMinutes = 0,
    ) {}
}
