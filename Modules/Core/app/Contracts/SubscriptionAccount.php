<?php

namespace Modules\Core\Contracts;

use Illuminate\Validation\ValidationException;
use Modules\Core\DTOs\SubscriptionInvoiceSummary;
use Modules\Core\DTOs\SubscriptionSummary;

/**
 * The current tenant's own subscription to the platform (Step 7.4), for Settings → Subscription and the reminders. Platform implements
 * it; without Platform there is none.
 */
interface SubscriptionAccount
{
    public function current(): ?SubscriptionSummary;

    /**
     * The tenant's platform invoices, newest first.
     *
     * @return list<SubscriptionInvoiceSummary>
     */
    public function invoices(): array;

    /**
     * The PDF of one of the tenant's own invoices, or null when it is not theirs.
     */
    public function invoicePdf(int $invoiceId): ?string;

    /**
     * Cancels at the end of the trial or the paid period.
     *
     * @throws ValidationException
     */
    public function cancel(string $reason): void;

    /**
     * Takes back a cancellation that has not taken effect yet.
     *
     * @throws ValidationException
     */
    public function resume(): void;
}
