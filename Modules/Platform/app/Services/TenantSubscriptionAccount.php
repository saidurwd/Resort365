<?php

namespace Modules\Platform\Services;

use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\SubscriptionAccount;
use Modules\Core\DTOs\SubscriptionInvoiceSummary;
use Modules\Core\DTOs\SubscriptionSummary;
use Modules\Platform\Actions\CancelSubscription;
use Modules\Platform\Actions\ResumeSubscription;
use Modules\Platform\Enums\PlatformInvoiceStatus;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\PlatformInvoice;
use Modules\Platform\Models\Subscription;

/**
 * The current tenant's subscription and platform invoices, for its own pages (Step 7.4).
 */
class TenantSubscriptionAccount implements SubscriptionAccount
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PlatformInvoiceDocuments $documents,
    ) {}

    public function current(): ?SubscriptionSummary
    {
        $subscription = $this->subscription();

        if (! $subscription instanceof Subscription) {
            return null;
        }

        $endsAt = $subscription->endsAt();
        $overdue = PlatformInvoice::query()->where('tenant_id', $subscription->tenant_id)->where('status', PlatformInvoiceStatus::Open->value)->whereDate('due_on', '<', today())->get()
            ->reduce(fn (BigDecimal $sum, PlatformInvoice $invoice): BigDecimal => $sum->plus($invoice->balance()), BigDecimal::zero());

        return new SubscriptionSummary(
            $subscription->plan->name, $subscription->status->value, $subscription->status->label(), $subscription->status->color(), $subscription->billing_period->label(),
            $subscription->price(), $subscription->plan->currency_code, $endsAt instanceof Carbon ? CarbonImmutable::instance($endsAt) : null, $subscription->cancel_at_period_end,
            $subscription->status === SubscriptionStatus::Trialing && $endsAt instanceof Carbon && $endsAt->lte(now()->addDays((int) config('platform.billing.trial_warning_days'))),
            (string) $overdue->toScale(2),
        );
    }

    public function invoices(): array
    {
        return PlatformInvoice::query()->where('tenant_id', $this->tenantId())->orderByDesc('issued_on')->orderByDesc('id')->get()
            ->map(fn (PlatformInvoice $invoice): SubscriptionInvoiceSummary => new SubscriptionInvoiceSummary(
                $invoice->id, $invoice->number, $invoice->description, CarbonImmutable::instance($invoice->issued_on), CarbonImmutable::instance($invoice->due_on),
                $invoice->currency_code, $invoice->amount, $invoice->balance(), $invoice->status->label(), $invoice->status->color(), $invoice->isOverdue(),
            ))->all();
    }

    public function invoicePdf(int $invoiceId): ?string
    {
        $invoice = PlatformInvoice::query()->where('tenant_id', $this->tenantId())->find($invoiceId);

        return $invoice instanceof PlatformInvoice ? $this->documents->pdf($invoice) : null;
    }

    public function cancel(string $reason): void
    {
        $this->attempt(fn (): Subscription => CancelSubscription::make()->handle($this->subscription() ?? throw new PlatformRuleViolated(__('There is no subscription to cancel.')), $reason));
    }

    public function resume(): void
    {
        $this->attempt(fn (): Subscription => ResumeSubscription::make()->handle($this->subscription() ?? throw new PlatformRuleViolated(__('There is no subscription to keep.'))));
    }

    private function subscription(): ?Subscription
    {
        return Subscription::query()->with('plan')->where('tenant_id', $this->tenantId())->where('status', '!=', SubscriptionStatus::Cancelled->value)->latest('id')->first();
    }

    private function tenantId(): int
    {
        return $this->context->tenantOrFail(self::class)->id;
    }

    /**
     * @param  callable(): mixed  $step
     *
     * @throws ValidationException
     */
    private function attempt(callable $step): void
    {
        try {
            $step();
        } catch (PlatformRuleViolated $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }
    }
}
