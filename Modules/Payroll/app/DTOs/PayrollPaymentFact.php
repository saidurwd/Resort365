<?php

namespace Modules\Payroll\DTOs;

use App\Support\DTOs\Data;

/**
 * A payroll run's payment as Accounting books it (Step 6.5): the net pay leaving cash or the bank.
 */
final readonly class PayrollPaymentFact extends Data
{
    public function __construct(
        public int $runId,
        public string $runNo,
        public int $propertyId,
        public string $date,
        public string $amount,
        public string $method,
        public ?string $reference,
    ) {}
}
