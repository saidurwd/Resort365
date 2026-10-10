<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Modules\Accounting\Actions\DiscardJournalDraft;
use Modules\Accounting\Actions\PostJournalEntry;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Enums\PostingKey;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\JournalEntry;
use Modules\Billing\Contracts\LedgerFacts;
use Modules\Billing\DTOs\ChargeFact;
use Modules\Billing\DTOs\CityLedgerTransferFact;
use Modules\Billing\DTOs\InvoiceFact;
use Modules\Billing\DTOs\PaymentFact;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Enums\RefundKind;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\CancellationFact;

/**
 * Turns operational events into balanced journal entries (ARCHITECTURE §7). Operational modules never
 * write the ledger: they emit events and Accounting reads the facts through their contracts.
 *
 * Every posting is idempotent: the entry carries its source (`source_type`, `source_id`,
 * `source_event`), which is unique, so an event handled twice posts once. A posting whose date falls
 * in a closed period goes into the first open period after it, with the real date in the reference.
 * Each method returns the entry, or null when there was nothing to post or it was posted before.
 */
class PostingService
{
    public function __construct(
        private readonly LedgerFacts $facts,
        private readonly ReservationLookup $reservations,
        private readonly AccountResolver $accounts,
        private readonly NightRevenueBuilder $revenue,
        private readonly JournalWriter $writer,
        private readonly PostJournalEntry $post,
        private readonly DiscardJournalDraft $discard,
    ) {}

    /**
     * The day's folio charges: Dr guest ledger / Cr revenue by charge code and taxes payable.
     */
    public function nightRevenue(int $propertyId, string $date): ?JournalEntry
    {
        $built = $this->revenue->build($this->facts->chargesOn($propertyId, $date));

        if ($built['revenue'] === [] && $built['taxes'] === []) {
            return null;
        }

        return $this->record('property_day', $propertyId, 'revenue:'.$date, $propertyId, $date, __('Room and guest charges of :date', ['date' => $date]), null, $this->chargeLines($built, 1));
    }

    /**
     * A charge voided after its day was posted: the mirror of its posting.
     */
    public function voidedCharge(int $folioLineId): ?JournalEntry
    {
        $charge = $this->facts->charge($folioLineId);

        if (! $charge instanceof ChargeFact || ! $this->dayPosted($charge->propertyId, $charge->postingDate)) {
            return null;
        }

        $built = $this->revenue->build([$charge]);

        return $this->record('folio_line', $folioLineId, 'voided', $charge->propertyId, $charge->postingDate, __('Voided charge of :date', ['date' => $charge->postingDate]), null, $this->chargeLines($built, -1));
    }

    /**
     * A payment or refund: deposits sit in Customer Advances until the stay is invoiced.
     */
    public function payment(int $paymentId): ?JournalEntry
    {
        $payment = $this->facts->payment($paymentId);

        if (! $payment instanceof PaymentFact) {
            return null;
        }

        $tender = $this->accounts->method($payment->method);
        $amount = $payment->amount;

        if ($payment->type === PaymentType::Refund) {
            $held = in_array($payment->refundKind, [RefundKind::Cancellation, RefundKind::SecurityDeposit], true);
            $against = $this->accounts->fixed($held ? PostingKey::CustomerAdvances : PostingKey::GuestLedger);

            return $this->record('payment', $paymentId, 'refunded', $payment->propertyId, $payment->businessDate, __('Refund :receipt', ['receipt' => $payment->receiptNo]), $payment->receiptNo,
                [$this->line($against, $amount, $payment->propertyId), $this->line($tender, '-'.$amount, $payment->propertyId)]);
        }

        $credit = match (true) {
            $payment->type === PaymentType::Deposit, $payment->type === PaymentType::SecurityDeposit => PostingKey::CustomerAdvances,
            $payment->reservationId === null => PostingKey::CityLedger,
            default => PostingKey::GuestLedger,
        };

        return $this->record('payment', $paymentId, 'received', $payment->propertyId, $payment->businessDate, __('Payment :receipt', ['receipt' => $payment->receiptNo]), $payment->receiptNo,
            [$this->line($tender, $amount, $payment->propertyId), $this->line($this->accounts->fixed($credit), '-'.$amount, $payment->propertyId)]);
    }

    /**
     * Check-out: the booking's deposits are applied to its folio, Dr customer advances / Cr guest ledger.
     */
    public function invoice(int $invoiceId): ?JournalEntry
    {
        $invoice = $this->facts->invoice($invoiceId);

        if (! $invoice instanceof InvoiceFact || ! BigDecimal::of($invoice->depositsApplied)->isPositive()) {
            return null;
        }

        return $this->record('invoice', $invoiceId, 'deposits_applied', $invoice->propertyId, $invoice->issueDate, __('Deposits applied to invoice :no', ['no' => $invoice->invoiceNo]), $invoice->invoiceNo, [
            $this->line($this->accounts->fixed(PostingKey::CustomerAdvances), $invoice->depositsApplied, $invoice->propertyId),
            $this->line($this->accounts->fixed(PostingKey::GuestLedger), '-'.$invoice->depositsApplied, $invoice->propertyId),
        ]);
    }

    /**
     * A folio balance moved to a company account: Dr city ledger / Cr guest ledger.
     */
    public function cityLedgerTransfer(int $entryId): ?JournalEntry
    {
        $transfer = $this->facts->cityLedgerTransfer($entryId);

        if (! $transfer instanceof CityLedgerTransferFact) {
            return null;
        }

        return $this->record('city_ledger_entry', $entryId, 'transferred', $transfer->propertyId, $transfer->date, $transfer->description, null, [
            $this->line($this->accounts->fixed(PostingKey::CityLedger), $transfer->amount, $transfer->propertyId, party: ['company', $transfer->companyId]),
            $this->line($this->accounts->fixed(PostingKey::GuestLedger), '-'.$transfer->amount, $transfer->propertyId),
        ]);
    }

    /**
     * A cancellation fee kept from the deposits: Dr customer advances / Cr cancellation revenue (never more than was paid).
     */
    public function cancellationFee(int $reservationId): ?JournalEntry
    {
        $cancellation = $this->reservations->cancellation($reservationId);

        if (! $cancellation instanceof CancellationFact) {
            return null;
        }

        $fee = BigDecimal::min(BigDecimal::of($cancellation->fee), BigDecimal::of($this->facts->depositsPaid($reservationId)))->toScale(2);

        if (! $fee->isPositive()) {
            return null;
        }

        return $this->record('reservation', $reservationId, 'cancellation_fee', $cancellation->propertyId, $cancellation->date, __('Cancellation fee of booking :code', ['code' => $cancellation->code]), $cancellation->code, [
            $this->line($this->accounts->fixed(PostingKey::CustomerAdvances), (string) $fee, $cancellation->propertyId),
            $this->line($this->accounts->fixed(PostingKey::CancellationRevenue), '-'.$fee, $cancellation->propertyId),
        ]);
    }

    /**
     * @param  array{revenue: array<string, array{code: ?string, category: string, amount: string}>, taxes: array<string, string>, total: string}  $built
     * @param  int  $sign  1 for a posting, -1 for its mirror
     * @return list<array<string, mixed>>
     */
    private function chargeLines(array $built, int $sign): array
    {
        $byAccount = [];
        $add = function (int $account, string $debit) use (&$byAccount): void {
            $byAccount[$account] = (string) BigDecimal::of($byAccount[$account] ?? '0')->plus($debit)->toScale(2);
        };

        $add($this->accounts->fixed(PostingKey::GuestLedger), $sign > 0 ? $built['total'] : (string) BigDecimal::of($built['total'])->negated());

        foreach ($built['revenue'] as $row) {
            $add($this->accounts->charge($row['code'], $row['category']), $sign > 0 ? (string) BigDecimal::of($row['amount'])->negated() : $row['amount']);
        }

        foreach ($built['taxes'] as $name => $amount) {
            $add($this->accounts->tax($name), $sign > 0 ? (string) BigDecimal::of($amount)->negated() : $amount);
        }

        return array_map(fn (int $account): array => ['account_id' => $account, 'debit' => $byAccount[$account]], array_keys($byAccount));
    }

    /**
     * @param  array{0: string, 1: int}|null  $party
     * @return array<string, mixed> a line with a signed debit amount (a negative one is a credit)
     */
    private function line(int $accountId, string $amount, int $propertyId, ?array $party = null): array
    {
        return ['account_id' => $accountId, 'debit' => $amount, 'property_id' => $propertyId, 'party_type' => $party[0] ?? null, 'party_id' => $party[1] ?? null];
    }

    /**
     * @param  list<array<string, mixed>>  $signed  lines with a signed `debit` amount
     */
    private function record(string $sourceType, int $sourceId, string $event, int $propertyId, string $date, string $description, ?string $reference, array $signed): ?JournalEntry
    {
        if ($this->exists($sourceType, $sourceId, $event)) {
            return null;
        }

        $lines = [];

        foreach ($signed as $line) {
            $amount = BigDecimal::of((string) $line['debit']);

            if ($amount->isZero()) {
                continue;
            }

            $lines[] = [...$line, 'property_id' => $line['property_id'] ?? $propertyId, 'debit' => $amount->isPositive() ? (string) $amount->toScale(2) : null, 'credit' => $amount->isNegative() ? (string) $amount->abs()->toScale(2) : null];
        }

        if ($lines === []) {
            return null;
        }

        [$entryDate, $moved] = $this->postingDate($date);
        $this->discardStale($sourceType, $sourceId, $event);
        try {
            $draft = $this->writer->saveDraft(null, [
                'entry_date' => $entryDate, 'description' => $description, 'reference' => $moved ? trim(($reference ?? '').' '.__('(dated :date)', ['date' => $date])) : $reference,
                'source_type' => $sourceType, 'source_id' => $sourceId, 'source_event' => $event,
            ], $lines);

            return $this->post->handle($draft);
        } catch (AccountingRuleViolated $exception) {
            $this->discardStale($sourceType, $sourceId, $event);

            throw $exception;
        } catch (QueryException $exception) {
            if ($this->exists($sourceType, $sourceId, $event)) {
                return null;
            }

            throw $exception;
        }
    }

    /**
     * A draft left behind by an interrupted posting would block the source key: remove it.
     */
    private function discardStale(string $sourceType, int $sourceId, string $event): void
    {
        JournalEntry::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->where('source_event', $event)
            ->where('status', JournalStatus::Draft->value)->get()->each(fn (JournalEntry $entry) => $this->discard->handle($entry));
    }

    private function exists(string $sourceType, int $sourceId, string $event): bool
    {
        return JournalEntry::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->where('source_event', $event)
            ->where('status', '!=', JournalStatus::Draft->value)->exists();
    }

    private function dayPosted(int $propertyId, string $date): bool
    {
        return $this->exists('property_day', $propertyId, 'revenue:'.$date);
    }

    /**
     * The date to post on: the date itself, or the first day of the next open period if its period is closed.
     *
     * @return array{0: string, 1: bool} date and whether it was moved
     *
     * @throws AccountingRuleViolated
     */
    private function postingDate(string $date): array
    {
        $period = FiscalPeriod::query()->where('starts_on', '<=', $date)->where('ends_on', '>=', $date)->first();

        if (! $period instanceof FiscalPeriod) {
            throw new AccountingRuleViolated(__('No fiscal period covers :date: create the fiscal year first.', ['date' => $date]));
        }

        if ($period->status === PeriodStatus::Open) {
            return [$date, false];
        }

        $next = FiscalPeriod::query()->where('status', PeriodStatus::Open->value)->where('starts_on', '>', $date)->orderBy('starts_on')->first();

        if (! $next instanceof FiscalPeriod) {
            throw new AccountingRuleViolated(__('The period of :date is closed and no later period is open.', ['date' => $date]));
        }

        return [$next->starts_on->toDateString(), true];
    }
}
