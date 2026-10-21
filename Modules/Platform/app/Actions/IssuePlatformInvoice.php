<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Platform\Contracts\PaymentGateway;
use Modules\Platform\Enums\PlatformInvoiceStatus;
use Modules\Platform\Enums\PlatformPaymentMethod;
use Modules\Platform\Models\PlatformInvoice;
use Modules\Platform\Models\Subscription;
use Modules\Platform\Services\PlatformAudit;

/**
 * Bills a subscription for one period at its plan's current price (Step 7.4): number `INV-{year}-{seq}` taken under a lock, so numbers
 * have no gaps; due after `platform.billing.invoice_due_days`. The gateway may collect it at once (a card later); manual invoicing
 * leaves it open for the tenant to pay.
 */
class IssuePlatformInvoice extends Action
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PlatformAudit $audit,
    ) {}

    public function handle(Subscription $subscription, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): PlatformInvoice
    {
        $invoice = $this->transaction(function () use ($subscription, $periodStart, $periodEnd): PlatformInvoice {
            $today = CarbonImmutable::today();
            $plan = $subscription->plan;

            $invoice = PlatformInvoice::query()->create([
                'number' => $this->nextNumber($today->year), 'tenant_id' => $subscription->tenant_id, 'subscription_id' => $subscription->id,
                'status' => PlatformInvoiceStatus::Open, 'description' => __(':plan plan, :period', ['plan' => $plan->name, 'period' => mb_strtolower($subscription->billing_period->label())]),
                'period_start' => $periodStart, 'period_end' => $periodEnd, 'issued_on' => $today, 'due_on' => $today->addDays((int) config('platform.billing.invoice_due_days')),
                'currency_code' => $plan->currency_code, 'amount' => $subscription->price(), 'paid_amount' => '0',
            ]);
            $this->audit->log(null, 'invoice.issued', __('Invoice :number issued', ['number' => $invoice->number]), $subscription->tenant_id, ['invoice' => $invoice->id, 'amount' => $invoice->amount]);

            if (BigDecimal::of($invoice->amount)->isZero()) {
                $invoice->forceFill(['status' => PlatformInvoiceStatus::Paid, 'paid_at' => now()])->save();
            }

            return $invoice;
        });

        if ($invoice->status === PlatformInvoiceStatus::Open && ($reference = $this->gateway->collect($invoice)) !== null) {
            RecordPlatformPayment::make()->handle($invoice, $invoice->balance(), PlatformPaymentMethod::Card, $reference, CarbonImmutable::today());
        }

        return $invoice->refresh();
    }

    /** The next number of the year; call inside the transaction that saves the invoice. */
    private function nextNumber(int $year): string
    {
        $prefix = 'INV-'.$year.'-';
        $last = PlatformInvoice::query()->where('number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('number')->value('number');
        $sequence = is_string($last) ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
