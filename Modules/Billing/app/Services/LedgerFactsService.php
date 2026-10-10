<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Modules\Billing\Contracts\LedgerFacts;
use Modules\Billing\DTOs\ChargeFact;
use Modules\Billing\DTOs\CityLedgerTransferFact;
use Modules\Billing\DTOs\InvoiceFact;
use Modules\Billing\DTOs\PaymentFact;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;

class LedgerFactsService implements LedgerFacts
{
    private const array BILLED = [FolioLineType::Charge, FolioLineType::Adjustment];

    public function chargesOn(int $propertyId, string $date): array
    {
        return FolioLine::query()->with(['chargeCode', 'folio'])->where('property_id', $propertyId)->where('posting_date', $date)
            ->where('is_voided', false)->where('revenue_posted_by_source', false)
            ->whereIn('line_type', array_map(fn (FolioLineType $type): string => $type->value, self::BILLED))
            ->orderBy('id')->get()->map(fn (FolioLine $line): ChargeFact => $this->chargeFact($line))->all();
    }

    public function charge(int $folioLineId): ?ChargeFact
    {
        $line = FolioLine::query()->with(['chargeCode', 'folio'])->find($folioLineId);

        return $line instanceof FolioLine && in_array($line->line_type, self::BILLED, true) && ! $line->revenue_posted_by_source ? $this->chargeFact($line) : null;
    }

    public function payment(int $paymentId): ?PaymentFact
    {
        $payment = Payment::query()->find($paymentId);

        return $payment instanceof Payment && $payment->status === PaymentStatus::Succeeded ? $this->paymentFact($payment) : null;
    }

    public function invoice(int $invoiceId): ?InvoiceFact
    {
        $invoice = Invoice::query()->with('folio')->find($invoiceId);

        if (! $invoice instanceof Invoice) {
            return null;
        }

        $first = $invoice->folio?->type === FolioType::Guest && $invoice->reservation_id !== null
            && ! Invoice::query()->where('folio_id', $invoice->folio_id)->where('id', '<', $invoice->id)->exists();

        return new InvoiceFact(
            $invoice->id, $invoice->property_id, $invoice->invoice_no, $invoice->issue_date->toDateString(), $invoice->folio_id, $invoice->reservation_id,
            $first ? $this->depositsPaid((int) $invoice->reservation_id) : '0.00',
        );
    }

    public function cityLedgerTransfer(int $entryId): ?CityLedgerTransferFact
    {
        $entry = CityLedgerEntry::query()->find($entryId);

        return $entry instanceof CityLedgerEntry && $entry->folio_id !== null
            ? new CityLedgerTransferFact($entry->id, $entry->property_id, $entry->company_id, $entry->folio_id, Folio::query()->whereKey($entry->folio_id)->value('reservation_id'), $entry->posted_on->toDateString(), (string) BigDecimal::of($entry->amount)->toScale(2), $entry->description)
            : null;
    }

    public function depositsPaid(int $reservationId): string
    {
        $sum = Payment::query()->where('reservation_id', $reservationId)->where('payment_type', PaymentType::Deposit->value)
            ->where('status', PaymentStatus::Succeeded->value)->get()
            ->reduce(fn (BigDecimal $total, Payment $payment): BigDecimal => $total->plus($payment->amount), BigDecimal::zero());

        return (string) $sum->toScale(2);
    }

    public function chargeCodes(): array
    {
        return ChargeCode::query()->orderBy('sort_order')->orderBy('code')->get()
            ->map(fn (ChargeCode $code): array => ['code' => $code->code, 'name' => $code->name, 'category' => $code->category->value])->all();
    }

    public function history(): array
    {
        $dates = FolioLine::query()->whereIn('line_type', array_map(fn (FolioLineType $type): string => $type->value, self::BILLED))
            ->where('is_voided', false)->where('revenue_posted_by_source', false)
            ->select(['property_id', 'posting_date'])->distinct()->orderBy('posting_date')->get();

        return [
            'payments' => Payment::query()->where('status', PaymentStatus::Succeeded->value)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'invoices' => Invoice::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'transfers' => CityLedgerEntry::query()->whereNotNull('folio_id')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'charge_dates' => $dates->map(fn (FolioLine $line): array => ['property_id' => $line->property_id, 'date' => $line->posting_date->toDateString()])->all(),
        ];
    }

    private function chargeFact(FolioLine $line): ChargeFact
    {
        $taxLines = array_map(fn (string $amount): string => (string) BigDecimal::of($amount)->toScale(2), $line->tax_lines ?? []);

        return new ChargeFact(
            $line->id, $line->property_id, $line->folio_id, $line->folio->reservation_id, $line->posting_date->toDateString(),
            $line->chargeCode?->code, ($line->chargeCode !== null ? $line->chargeCode->category->value : 'misc'),
            (string) BigDecimal::of($line->amount)->toScale(2), (string) BigDecimal::of($line->tax_amount)->toScale(2), $taxLines,
            (string) BigDecimal::of($line->meal_amount ?? '0')->toScale(2), $line->is_voided,
        );
    }

    private function paymentFact(Payment $payment): PaymentFact
    {
        return new PaymentFact(
            $payment->id, $payment->property_id, $payment->receipt_no, $payment->payment_type, $payment->method, (string) BigDecimal::of($payment->amount)->toScale(2),
            ($payment->business_date ?? $payment->received_at)->toDateString(), $payment->reservation_id, $payment->folio_id, $payment->refund_kind,
        );
    }
}
