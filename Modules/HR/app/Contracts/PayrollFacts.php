<?php

namespace Modules\HR\Contracts;

use Modules\HR\DTOs\AttendanceFacts;
use Modules\HR\DTOs\BankDetails;
use Modules\HR\DTOs\PayrollEmployee;

/**
 * What the payroll run needs from HR (Step 6.5): who worked at a property in a month, their attendance, and their bank
 * details. Months are "Y-m".
 */
interface PayrollFacts
{
    /**
     * Employees of the property who joined by the end of the month and had not left before it began, by name.
     *
     * @return list<PayrollEmployee>
     */
    public function employees(int $propertyId, string $month): array;

    /**
     * Attendance figures per employee id for the month, counted up to `$upTo` (a date; days after it have not happened).
     *
     * @return array<int, AttendanceFacts>
     */
    public function attendance(int $propertyId, string $month, string $upTo): array;

    public function bankDetails(int $employeeId): ?BankDetails;
}
