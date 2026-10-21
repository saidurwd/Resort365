<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * A tenant's subscription as the tenant sees it (Step 7.4). `status` is trialing, active or cancelled; `endsAt` is when the trial or
 * the paid period ends; `remindTrial` says the trial is in its last days; `overdue` is the total of invoices past their due date.
 */
final readonly class SubscriptionSummary extends Data
{
    public function __construct(
        public string $planName,
        public string $status,
        public string $statusLabel,
        public string $statusColor,
        public string $billingPeriod,
        public string $price,
        public string $currencyCode,
        public ?CarbonImmutable $endsAt,
        public bool $cancelAtPeriodEnd,
        public bool $remindTrial,
        public string $overdue,
    ) {}

    public function trialing(): bool
    {
        return $this->status === 'trialing';
    }

    public function hasOverdue(): bool
    {
        return BigDecimal::of($this->overdue)->isPositive();
    }
}
