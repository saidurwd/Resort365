<?php

namespace Modules\Platform\Contracts;

use Modules\Platform\Models\PlatformInvoice;

/**
 * How platform invoices get paid (Step 7.4). Manual invoicing first: the tenant pays by bank transfer and an admin records it. A card
 * gateway (Cashier/Stripe, Phase 8) implements the same contract and charges the tenant's saved card when an invoice is issued.
 */
interface PaymentGateway
{
    /** The gateway's key, stored on the subscription (`manual`, `stripe`). */
    public function key(): string;

    /**
     * Tries to collect a newly issued invoice; returns the gateway's payment reference when it was paid, null when the tenant pays
     * it themselves.
     */
    public function collect(PlatformInvoice $invoice): ?string;
}
