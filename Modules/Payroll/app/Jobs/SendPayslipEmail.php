<?php

namespace Modules\Payroll\Jobs;

use App\Support\Tenancy\InteractsWithTenant;
use App\Support\Tenancy\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Notifications\PayslipMessage;
use Modules\Payroll\Services\PayslipDocuments;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Emails one payslip to the employee in the background (the PDF is made when it runs), as the tenant that dispatched it (Step 6.5).
 */
class SendPayslipEmail implements ShouldQueue, TenantAware
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithTenant;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $payslipId) {}

    public function handle(PayslipDocuments $documents, PropertyDirectory $properties): void
    {
        $payslip = Payslip::query()->with('run')->find($this->payslipId);

        if (! $payslip instanceof Payslip || $payslip->employee_email === null) {
            return;
        }

        Notification::route('mail', [$payslip->employee_email => $payslip->employee_name])->notifyNow(new PayslipMessage(
            $payslip->employee_name, $payslip->run->period->format('F Y'), $properties->find($payslip->property_id)->name ?? (string) config('app.name'), $documents->fileName($payslip), $documents->pdf($payslip),
        ));
        $payslip->forceFill(['emailed_at' => now()])->save();
    }
}
