<?php

namespace Modules\Platform\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Platform\Enums\PlatformInvoiceStatus;
use Modules\Platform\Enums\PlatformPaymentMethod;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\PlatformInvoice;
use Modules\Platform\Models\PlatformPayment;
use Modules\Platform\Services\PlatformAudit;

/**
 * Records what a tenant paid against a platform invoice (Step 7.4), never more than its balance. Paid in full, the invoice is closed,
 * and a tenant suspended for non-payment is reactivated once nothing it owes is overdue.
 */
class RecordPlatformPayment extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @throws PlatformRuleViolated
     */
    public function handle(PlatformInvoice $invoice, string $amount, PlatformPaymentMethod $method, ?string $reference, CarbonImmutable $paidOn, ?PlatformAdmin $admin = null): PlatformPayment
    {
        if ($invoice->status !== PlatformInvoiceStatus::Open) {
            throw new PlatformRuleViolated(__('Invoice :number is :status, so it takes no payments.', ['number' => $invoice->number, 'status' => mb_strtolower($invoice->status->label())]));
        }

        $paid = BigDecimal::of($amount);

        if ($paid->isLessThanOrEqualTo(0) || $paid->isGreaterThan($invoice->balance())) {
            throw new PlatformRuleViolated(__('Enter an amount more than 0 and at most the balance of :balance.', ['balance' => $invoice->balance()]));
        }

        if ($paidOn->isFuture()) {
            throw new PlatformRuleViolated(__('The payment date cannot be in the future.'));
        }

        return $this->transaction(function () use ($invoice, $paid, $method, $reference, $paidOn, $admin): PlatformPayment {
            $invoice = PlatformInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $payment = PlatformPayment::query()->create([
                'platform_invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id, 'amount' => (string) $paid->toScale(2), 'method' => $method,
                'reference' => $reference !== null && trim($reference) !== '' ? trim($reference) : null, 'paid_on' => $paidOn, 'recorded_by' => $admin?->id,
            ]);

            $total = BigDecimal::of($invoice->paid_amount)->plus($paid)->toScale(2);
            $invoice->forceFill(['paid_amount' => (string) $total]);

            if ($total->isGreaterThanOrEqualTo($invoice->amount)) {
                $invoice->forceFill(['status' => PlatformInvoiceStatus::Paid, 'paid_at' => now()]);
            }

            $invoice->save();
            $this->audit->log($admin, 'invoice.payment', __(':amount received for invoice :number', ['amount' => $payment->amount, 'number' => $invoice->number]), $invoice->tenant_id, ['invoice' => $invoice->id, 'payment' => $payment->id]);
            $this->liftSuspension($invoice->tenant, $admin);

            return $payment;
        });
    }

    private function liftSuspension(Tenant $tenant, ?PlatformAdmin $admin): void
    {
        $overdue = PlatformInvoice::query()->where('tenant_id', $tenant->id)->where('status', PlatformInvoiceStatus::Open->value)->whereDate('due_on', '<', today())->exists();

        if ($tenant->status === TenantStatus::Suspended && $tenant->suspended_for === 'unpaid' && ! $overdue) {
            ReactivateTenant::make()->handle($tenant, $admin);
        }
    }
}
