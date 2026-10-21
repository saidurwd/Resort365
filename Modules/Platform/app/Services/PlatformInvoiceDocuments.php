<?php

namespace Modules\Platform\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Platform\Models\PlatformInvoice;

/**
 * The PDF of a platform invoice (Step 7.4), the same for the console and the tenant.
 */
class PlatformInvoiceDocuments
{
    public function pdf(PlatformInvoice $invoice): string
    {
        return Pdf::loadView('platform::invoices.pdf', ['invoice' => $invoice, 'tenant' => $invoice->tenant])->setPaper('a4')->output();
    }
}
