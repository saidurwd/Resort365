<?php

namespace Modules\Payroll\Contracts;

use Modules\Payroll\DTOs\LoanFact;
use Modules\Payroll\DTOs\PayrollPaymentFact;
use Modules\Payroll\DTOs\PayrollRunFact;

/**
 * What Accounting reads from Payroll to book salaries, their payment and staff loans (Step 6.5); it never reads Payroll's models.
 */
interface PayrollLedgerFacts
{
    /** An approved or paid run, or null. */
    public function run(int $runId): ?PayrollRunFact;

    /** A paid run's payment, or null. */
    public function payment(int $runId): ?PayrollPaymentFact;

    public function loan(int $loanId): ?LoanFact;

    /** A written-off installment (`id` is the installment's), or null. */
    public function waiver(int $installmentId): ?LoanFact;

    /**
     * Everything that could be booked, for back-posting.
     *
     * @return array{approved: list<int>, paid: list<int>, loans: list<int>, waivers: list<int>}
     */
    public function history(): array;
}
