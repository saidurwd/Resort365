<?php

namespace Modules\Billing\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Billing\Models\Invoice;

/**
 * Invoices of properties the user cannot access never load (BelongsToProperty).
 */
class InvoicePolicy
{
    public function view(Authenticatable&Authorizable $user, Invoice $invoice): bool
    {
        return $user->can('billing.invoice.view');
    }

    public function credit(Authenticatable&Authorizable $user, Invoice $invoice): bool
    {
        return $user->can('billing.credit-note.issue');
    }
}
