<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\FolioLedger;
use Modules\Billing\Services\TaxSplitter;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Guest\Contracts\GuestLookup;

/**
 * Issues a folio's invoice (ARCHITECTURE §5.9): sequential number, a frozen copy of its charges and
 * adjustments (voided lines left out), the tax breakdown by tax, what was paid (the deposit
 * included) and what went on account. Called inside the caller's transaction.
 */
class IssueInvoice extends Action
{
    public function __construct(
        private readonly FolioLedger $ledger,
        private readonly TaxSplitter $splitter,
        private readonly DocumentNumbers $numbers,
        private readonly GuestLookup $guests,
    ) {}

    public function handle(Folio $folio, ?int $userId = null): Invoice
    {
        $lines = FolioLine::query()->with('chargeCode')->where('folio_id', $folio->id)->where('is_voided', false)->orderBy('posting_date')->orderBy('id')->get();
        $billed = $lines->filter(fn (FolioLine $line): bool => in_array($line->line_type, [FolioLineType::Charge, FolioLineType::Adjustment], true));
        $sum = fn (iterable $rows, string $field): string => (string) collect($rows)->reduce(fn (BigDecimal $total, FolioLine $line): BigDecimal => $total->plus($line->{$field}), BigDecimal::zero())->toScale(2);
        $paid = BigDecimal::of($sum($lines->where('line_type', FolioLineType::Payment), 'total'))->minus($sum($lines->where('line_type', FolioLineType::Refund), 'total'));

        $invoice = Invoice::query()->create([
            'property_id' => $folio->property_id,
            'folio_id' => $folio->id,
            'reservation_id' => $folio->reservation_id,
            'invoice_no' => $this->numbers->next('invoice', $folio->property_id),
            'issue_date' => $this->ledger->businessDate($folio->property_id),
            'bill_to_type' => $folio->bill_to_type,
            'bill_to_id' => $folio->bill_to_id,
            'bill_to_name' => $folio->name,
            'bill_to_tax_number' => $folio->bill_to_type === BillTo::Company && $folio->bill_to_id !== null ? $this->guests->findCompany($folio->bill_to_id)?->taxNumber : null,
            'currency_code' => $folio->currency_code,
            'subtotal' => $sum($billed, 'amount'),
            'tax_total' => $sum($billed, 'tax_amount'),
            'total' => $sum($billed, 'total'),
            'paid' => (string) $paid->toScale(2),
            'on_account' => $sum($lines->where('line_type', FolioLineType::Transfer), 'total'),
            'tax_breakdown' => $this->splitter->sum($billed->map(fn (FolioLine $line): ?array => $line->tax_lines)),
            'status' => InvoiceStatus::Issued,
            'issued_by' => $userId,
        ]);

        foreach ($billed as $line) {
            $invoice->lines()->create([
                'property_id' => $folio->property_id, 'folio_line_id' => $line->id, 'service_date' => $line->posting_date->toDateString(),
                'charge_code' => $line->chargeCode?->code, 'description' => $line->description, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price,
                'amount' => $line->amount, 'tax_amount' => $line->tax_amount, 'total' => $line->total,
            ]);
        }

        CityLedgerEntry::query()->where('folio_id', $folio->id)->whereNull('invoice_id')->update(['invoice_id' => $invoice->id]);

        return $invoice;
    }
}
