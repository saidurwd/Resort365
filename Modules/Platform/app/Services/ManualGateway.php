<?php

namespace Modules\Platform\Services;

use Modules\Platform\Contracts\PaymentGateway;
use Modules\Platform\Models\PlatformInvoice;

/**
 * Manual invoicing (Step 7.4, Q12): nothing is charged; the tenant pays the invoice by bank transfer and a platform admin records it.
 */
class ManualGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function collect(PlatformInvoice $invoice): ?string
    {
        return null;
    }
}
