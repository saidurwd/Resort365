<?php

namespace Modules\Payroll\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Payroll\Models\Payslip;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * The payslip PDF (Step 6.5, A4, dompdf): the employee, the month, every line and the net pay.
 */
class PayslipDocuments
{
    public function __construct(private readonly PropertyDirectory $properties) {}

    public function pdf(Payslip $payslip): string
    {
        $payslip->loadMissing(['run', 'lines']);

        return Pdf::loadView('payroll::documents.payslip', ['payslip' => $payslip, 'run' => $payslip->run, 'property' => $this->properties->find($payslip->property_id)])->setPaper('a4')->output();
    }

    public function fileName(Payslip $payslip): string
    {
        return 'payslip-'.$payslip->employee_no.'-'.$payslip->run->period->format('Y-m').'.pdf';
    }
}
