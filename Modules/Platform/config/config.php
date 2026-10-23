<?php

return [
    'name' => 'Platform',

    // Platform admins must set up two-factor sign-in (Phase 7 review). null = in production only.
    'require_two_factor' => null,

    // Subscriptions and platform invoices (Step 7.4).
    'billing' => [
        // Days a platform invoice may stay unpaid before the tenant is suspended.
        'invoice_due_days' => 7,
        // Days before the end of a trial that its owner is reminded.
        'trial_warning_days' => 3,
        // Days a cancelled tenant's data is kept before `platform:purge-tenants` deletes it.
        'retention_days' => 30,
        // The payment gateway (manual invoicing first; Cashier/Stripe later).
        'gateway' => 'manual',
        // Who bills, and how to pay, printed on every platform invoice.
        'issuer' => 'Resort365',
        'payment_instructions' => 'Pay by bank transfer and quote the invoice number. Bank details are on your welcome email, or ask support.',
    ],
];
