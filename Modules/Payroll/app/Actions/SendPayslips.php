<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Jobs\SendPayslipEmail;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;

/**
 * Queues the payslips of an approved or paid run to be emailed (Step 6.5), skipping employees with no email address and
 * those who have already had theirs (unless asked to send again). Returns how many were queued and who was skipped.
 */
class SendPayslips extends Action
{
    /**
     * @return array{queued: int, no_email: list<string>, already: int}
     *
     * @throws PayrollRuleViolated
     */
    public function handle(PayrollRun $run, bool $again = false): array
    {
        if (! in_array($run->status, [RunStatus::Approved, RunStatus::Paid], true)) {
            throw new PayrollRuleViolated(__('Payslips are sent once the run is approved.'));
        }

        $queued = 0;
        $already = 0;
        $noEmail = [];

        foreach ($run->payslips()->get() as $payslip) {
            /** @var Payslip $payslip */
            if ($payslip->employee_email === null || $payslip->employee_email === '') {
                $noEmail[] = $payslip->employee_name;
            } elseif ($payslip->emailed_at !== null && ! $again) {
                $already++;
            } else {
                SendPayslipEmail::dispatch($payslip->id);
                $queued++;
            }
        }

        return ['queued' => $queued, 'no_email' => $noEmail, 'already' => $already];
    }
}
