<?php

return [
    'name' => 'Platform',

    // Subscriptions and platform invoices (Step 7.4).
    'billing' => [
        // Days a platform invoice may stay unpaid before the tenant is suspended.
        'invoice_due_days' => 7,
        // Days before the end of a trial that its owner is reminded.
        'trial_warning_days' => 3,
        // Days a cancelled tenant's data is kept before it may be purged. TODO(step-7.5): the purge.
        'retention_days' => 30,
        // The payment gateway (manual invoicing first; Cashier/Stripe later).
        'gateway' => 'manual',
        // Who bills, and how to pay, printed on every platform invoice.
        'issuer' => 'Resort365',
        'payment_instructions' => 'Pay by bank transfer and quote the invoice number. Bank details are on your welcome email, or ask support.',
    ],
];
