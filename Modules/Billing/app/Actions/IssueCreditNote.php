<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\Enums\CityLedgerStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\CreditNote;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\FolioLedger;
use Modules\Core\Contracts\DocumentNumbers;

/**
 * Corrects an issued invoice with a credit note (ARCHITECTURE §5.9; invoices never change). The
 * amount first reduces what the company still owes on the city ledger for this invoice; the rest
 * becomes refundable (refund_due), paid out with RefundPayment.
 */
class IssueCreditNote extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly FolioLedger $ledger,
    ) {}

    /**
     * @throws PaymentNotAllowed
     */
    public function handle(Invoice $invoice, string $amount, string $reason, ?int $userId = null): CreditNote
    {
        return $this->transaction(function () use ($invoice, $amount, $reason, $userId): CreditNote {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $credit = BigDecimal::of($amount)->toScale(2);
            $left = BigDecimal::of($locked->total)->minus($locked->credited);

            if (! $credit->isPositive() || $credit->isGreaterThan($left)) {
                throw new PaymentNotAllowed(__('A credit note must be more than zero and at most :left.', ['left' => (string) $left->toScale(2)]));
            }

            $toLedger = BigDecimal::zero();

            foreach (CityLedgerEntry::query()->where('invoice_id', $locked->id)->where('status', CityLedgerStatus::Open->value)->lockForUpdate()->get() as $entry) {
                $applied = BigDecimal::min($credit->minus($toLedger), BigDecimal::of($entry->open()));

                if ($applied->isPositive()) {
                    $credited = BigDecimal::of($entry->credited)->plus($applied)->toScale(2);
                    $entry->forceFill(['credited' => (string) $credited, 'status' => BigDecimal::of($entry->amount)->minus($entry->paid)->minus($credited)->isZero() ? CityLedgerStatus::Paid : CityLedgerStatus::Open])->save();
                    $toLedger = $toLedger->plus($applied);
                }
            }

            $note = CreditNote::query()->create([
                'property_id' => $locked->property_id,
                'invoice_id' => $locked->id,
                'credit_note_no' => $this->numbers->next('credit_note', $locked->property_id),
                'issue_date' => $this->ledger->businessDate($locked->property_id),
                'amount' => (string) $credit,
                'reason' => $reason,
                'applied_to_ledger' => (string) $toLedger->toScale(2),
                'refund_due' => (string) $credit->minus($toLedger)->toScale(2),
                'issued_by' => $userId,
            ]);

            $credited = BigDecimal::of($locked->credited)->plus($credit)->toScale(2);
            $locked->forceFill(['credited' => (string) $credited, 'status' => $credited->isEqualTo($locked->total) ? InvoiceStatus::Credited : InvoiceStatus::PartiallyCredited])->save();

            return $note;
        }, attempts: 3);
    }
}
