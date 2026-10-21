<?php

namespace Modules\Core\Services;

use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\SubscriptionAccount;
use Modules\Core\DTOs\SubscriptionSummary;

/**
 * Without Platform there is no subscription.
 */
class NoSubscriptionAccount implements SubscriptionAccount
{
    public function current(): ?SubscriptionSummary
    {
        return null;
    }

    public function invoices(): array
    {
        return [];
    }

    public function invoicePdf(int $invoiceId): ?string
    {
        return null;
    }

    public function cancel(string $reason): void
    {
        throw ValidationException::withMessages(['reason' => __('There is no subscription to cancel.')]);
    }

    public function resume(): void
    {
        throw ValidationException::withMessages(['reason' => __('There is no subscription to keep.')]);
    }
}
