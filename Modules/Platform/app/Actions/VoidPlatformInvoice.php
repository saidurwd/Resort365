<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Platform\Enums\PlatformInvoiceStatus;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\PlatformInvoice;
use Modules\Platform\Services\PlatformAudit;

/**
 * Voids an open platform invoice nothing has been paid on (Step 7.4), with a reason: billed by mistake, or forgiven. Its number stays
 * used.
 */
class VoidPlatformInvoice extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @throws PlatformRuleViolated
     */
    public function handle(PlatformInvoice $invoice, string $reason, ?PlatformAdmin $admin = null): PlatformInvoice
    {
        if (trim($reason) === '') {
            throw new PlatformRuleViolated(__('Say why the invoice is voided.'));
        }

        if ($invoice->status !== PlatformInvoiceStatus::Open || BigDecimal::of($invoice->paid_amount)->isPositive()) {
            throw new PlatformRuleViolated(__('Only an open invoice with nothing paid on it can be voided.'));
        }

        return $this->transaction(function () use ($invoice, $reason, $admin): PlatformInvoice {
            $invoice->forceFill(['status' => PlatformInvoiceStatus::Void, 'voided_at' => now(), 'void_reason' => trim($reason)])->save();
            $this->audit->log($admin, 'invoice.voided', __('Invoice :number voided', ['number' => $invoice->number]), $invoice->tenant_id, ['invoice' => $invoice->id, 'reason' => trim($reason)]);

            return $invoice;
        });
    }
}
