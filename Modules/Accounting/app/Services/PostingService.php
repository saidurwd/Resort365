<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Modules\Accounting\Actions\DiscardJournalDraft;
use Modules\Accounting\Actions\PostJournalEntry;
use Modules\Accounting\Actions\ReverseJournalEntry;
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
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Enums\RefundKind;
use Modules\Inventory\Contracts\StockFacts;
use Modules\Inventory\DTOs\StockAdjustmentFact;
use Modules\Inventory\DTOs\StockIssueFact;
use Modules\Procurement\Contracts\ProcurementFacts;
use Modules\Procurement\DTOs\ReceiptFact;
use Modules\Procurement\DTOs\ReturnFact;
use Modules\Procurement\DTOs\VendorBillFact;
use Modules\Procurement\DTOs\VendorPaymentFact;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\CancellationFact;
use Modules\Restaurant\Contracts\ConsumptionFacts;
use Modules\Restaurant\Contracts\RestaurantFacts;
use Modules\Restaurant\DTOs\BillFact;
use Modules\Restaurant\DTOs\ConsumptionFact;
use Modules\Restaurant\DTOs\SessionFact;
use Modules\Restaurant\DTOs\WastageFact;

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
        private readonly RestaurantFacts $restaurant,
        private readonly BillEntryBuilder $bills,
        private readonly ReverseJournalEntry $reverse,
        private readonly StockFacts $stock,
        private readonly ProcurementFacts $procurement,
        private readonly ConsumptionFacts $consumption,
    ) {}

    /**
     * The ingredients a settled bill used (Step 5.7): Dr cost of sales by class (staff meals and complimentary for a
     * comped bill) / Cr inventory by class.
     */
    public function saleConsumption(int $billId): ?JournalEntry
    {
        $fact = $this->consumption->consumption($billId);

        if (! $fact instanceof ConsumptionFact) {
            return null;
        }

        $lines = [];

        foreach ($fact->byClass as $class => $cost) {
            $lines[] = $this->line($this->accounts->saleCost($class, $fact->complimentary), $cost, $fact->propertyId);
            $lines[] = $this->line($this->accounts->stockInventory($class), '-'.$cost, $fact->propertyId);
        }

        return $this->record('pos_bill', $billId, 'consumed', $fact->propertyId, $fact->businessDate, __('Ingredients used by bill :no', ['no' => $fact->billNo]), $fact->billNo, $lines);
    }

    /**
     * A voided bill's ingredients went back into stock: the mirror of the consumption entry.
     */
    public function saleConsumptionReversed(int $billId): ?JournalEntry
    {
        $entry = JournalEntry::query()->where('source_type', 'pos_bill')->where('source_id', $billId)->where('source_event', 'consumed')->where('status', JournalStatus::Posted->value)->first();
        $fact = $this->consumption->consumption($billId);

        if (! $entry instanceof JournalEntry || ! $fact instanceof ConsumptionFact || $this->exists('pos_bill', $billId, 'consumption_reversed')) {
            return null;
        }

        [$date] = $this->postingDate($fact->businessDate);

        return $this->reverse->handle($entry, $date, null, __('Bill :no voided', ['no' => $fact->billNo]));
    }

    /**
     * Food written off (Step 5.7): Dr food wastage / Cr inventory by class.
     */
    public function wastage(int $wastageId): ?JournalEntry
    {
        $fact = $this->consumption->wastage($wastageId);

        if (! $fact instanceof WastageFact) {
            return null;
        }

        $lines = [];

        foreach ($fact->byClass as $class => $cost) {
            $lines[] = $this->line($this->accounts->wastageExpense(), $cost, $fact->propertyId);
            $lines[] = $this->line($this->accounts->stockInventory($class), '-'.$cost, $fact->propertyId);
        }

        return $this->record('wastage_entry', $wastageId, 'wasted', $fact->propertyId, $fact->date, __('Wastage :no', ['no' => $fact->wastageNo]), $fact->wastageNo, $lines);
    }

    /**
     * Goods received (Step 5.6): Dr inventory by class / Cr goods received not invoiced.
     */
    public function goodsReceipt(int $receiptId): ?JournalEntry
    {
        $receipt = $this->procurement->receipt($receiptId);

        if (! $receipt instanceof ReceiptFact) {
            return null;
        }

        $lines = [];
        $total = BigDecimal::zero();

        foreach ($receipt->byClass as $class => $value) {
            $lines[] = $this->line($this->accounts->stockInventory($class), $value, $receipt->propertyId);
            $total = $total->plus($value);
        }

        $lines[] = $this->line($this->accounts->fixed(PostingKey::Grni), '-'.$total->toScale(2), $receipt->propertyId, ['vendor', $receipt->vendorId]);

        return $this->record('goods_receipt', $receiptId, 'received', $receipt->propertyId, $receipt->date, __('Goods received :no', ['no' => $receipt->receiptNo]), $receipt->receiptNo, $lines);
    }

    /**
     * An approved vendor bill (Step 5.6): a goods bill clears GRNI (price variance to its own account), a service bill
     * books the expense with the department; the input VAT is recoverable; accounts payable is credited with the vendor.
     */
    public function vendorBill(int $billId): ?JournalEntry
    {
        $bill = $this->procurement->bill($billId);

        if (! $bill instanceof VendorBillFact) {
            return null;
        }

        $lines = [];

        if (BigDecimal::of($bill->grni)->isPositive() || $bill->expenseByClass === []) {
            $lines[] = $this->line($this->accounts->fixed(PostingKey::Grni), $bill->grni, $bill->propertyId);
            $lines[] = $this->line($this->accounts->fixed(PostingKey::PriceVariance), $bill->variance, $bill->propertyId);
        }

        foreach ($bill->expenseByClass as $class => $net) {
            $lines[] = [...$this->line($this->accounts->billExpense($class), $net, $bill->propertyId), 'department_id' => $bill->departmentId];
        }

        $lines[] = $this->line($this->accounts->fixed(PostingKey::InputVat), $bill->tax, $bill->propertyId);
        $lines[] = $this->line($this->accounts->fixed(PostingKey::AccountsPayable), '-'.$bill->total, $bill->propertyId, ['vendor', $bill->vendorId]);

        return $this->record('vendor_bill', $billId, 'approved', $bill->propertyId, $bill->date, __('Vendor bill :no', ['no' => $bill->billNo]), $bill->billNo, $lines);
    }

    /**
     * A vendor payment, allocated or an advance (Step 5.6): Dr accounts payable (with the vendor) / Cr cash or bank.
     */
    public function vendorPayment(int $paymentId): ?JournalEntry
    {
        $payment = $this->procurement->payment($paymentId);

        if (! $payment instanceof VendorPaymentFact) {
            return null;
        }

        return $this->record('vendor_payment', $paymentId, 'paid', $payment->propertyId, $payment->date, __('Vendor payment :no', ['no' => $payment->paymentNo]), $payment->paymentNo, [
            $this->line($this->accounts->fixed(PostingKey::AccountsPayable), $payment->amount, $payment->propertyId, ['vendor', $payment->vendorId]),
            $this->line($this->accounts->vendorMethod($payment->method), '-'.$payment->amount, $payment->propertyId),
        ]);
    }

    /**
     * Goods returned with a debit note (Step 5.6): the unbilled part reverses GRNI, the billed part (and its tax) reduces
     * accounts payable; inventory leaves at its cost and any difference to the credited value is price variance.
     */
    public function purchaseReturn(int $returnId): ?JournalEntry
    {
        $return = $this->procurement->purchaseReturn($returnId);

        if (! $return instanceof ReturnFact) {
            return null;
        }

        $lines = [
            $this->line($this->accounts->fixed(PostingKey::Grni), $return->grni, $return->propertyId),
            $this->line($this->accounts->fixed(PostingKey::AccountsPayable), (string) BigDecimal::of($return->billedNet)->plus($return->tax)->toScale(2), $return->propertyId, ['vendor', $return->vendorId]),
        ];

        foreach ($return->inventoryByClass as $class => $cost) {
            $lines[] = $this->line($this->accounts->stockInventory($class), '-'.$cost, $return->propertyId);
        }

        $lines[] = $this->line($this->accounts->fixed(PostingKey::InputVat), '-'.$return->tax, $return->propertyId);
        $lines[] = $this->line($this->accounts->fixed(PostingKey::PriceVariance), (string) BigDecimal::of($return->variance)->negated()->toScale(2), $return->propertyId);

        return $this->record('purchase_return', $returnId, 'returned', $return->propertyId, $return->date, __('Debit note :no', ['no' => $return->debitNoteNo]), $return->debitNoteNo, $lines);
    }

    /**
     * Stock issued to a department (Step 5.4): Dr the class's cost account (with the department) / Cr its inventory account.
     */
    public function stockIssue(int $issueId): ?JournalEntry
    {
        $issue = $this->stock->issue($issueId);

        if (! $issue instanceof StockIssueFact) {
            return null;
        }

        $lines = [];

        foreach ($issue->byClass as $class => $value) {
            $lines[] = [...$this->line($this->accounts->stockExpense($class), $value, $issue->propertyId), 'department_id' => $issue->departmentId];
            $lines[] = $this->line($this->accounts->stockInventory($class), '-'.$value, $issue->propertyId);
        }

        return $this->record('stock_issue', $issueId, 'issued', $issue->propertyId, $issue->date, __('Stock issue :no', ['no' => $issue->issueNo]), $issue->issueNo, $lines);
    }

    /**
     * Count differences and manual adjustments: a loss is Dr inventory shrinkage / Cr inventory, a gain the reverse.
     */
    public function stockAdjustment(string $sourceType, int $sourceId): ?JournalEntry
    {
        $adjustment = $this->stock->adjustment($sourceType, $sourceId);

        if (! $adjustment instanceof StockAdjustmentFact) {
            return null;
        }

        $lines = [];

        foreach ($adjustment->byClass as $class => $signed) {
            // $signed is the change in stock value: a loss is negative, so the shrinkage expense is its opposite.
            $expense = (string) BigDecimal::of($signed)->negated();
            $lines[] = $this->line($this->accounts->stockShrinkage(), $expense, $adjustment->propertyId);
            $lines[] = $this->line($this->accounts->stockInventory($class), $signed, $adjustment->propertyId);
        }

        return $this->record($sourceType, $sourceId, 'adjusted', $adjustment->propertyId, $adjustment->date, __('Stock adjustment :ref', ['ref' => $adjustment->reference]), $adjustment->reference, $lines);
    }

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
     * A settled restaurant bill: the tenders against food and beverage revenue per outlet, service charge, taxes and
     * tips. A room-charge bill debits the guest ledger here, and its folio line is flagged so the night audit leaves
     * the revenue alone.
     */
    public function restaurantBill(int $billId): ?JournalEntry
    {
        $bill = $this->restaurant->bill($billId);
        $built = $bill instanceof BillFact ? $this->bills->build($bill) : null;

        if (! $bill instanceof BillFact || $built === null) {
            return null;
        }

        $byAccount = [];
        $add = function (?int $account, string $debit) use (&$byAccount): void {
            if ($account !== null) {
                $byAccount[$account] = (string) BigDecimal::of($byAccount[$account] ?? '0')->plus($debit)->toScale(2);
            }
        };

        foreach ($built['tenders'] as $method => $amount) {
            $add($this->accounts->restaurantTender($method), $amount);
        }

        foreach ($built['revenue'] as $class => $amount) {
            $add($this->accounts->outletRevenue($bill->outletId, $class), (string) BigDecimal::of($amount)->negated());
        }

        $add($this->accounts->fixed(PostingKey::ServiceChargePayable), (string) BigDecimal::of($built['service'])->negated());

        foreach ($built['taxes'] as $name => $amount) {
            $add($this->accounts->tax($name), (string) BigDecimal::of($amount)->negated());
        }

        $add($this->accounts->fixed(PostingKey::TipsPayable), (string) BigDecimal::of($built['tips'])->negated());

        return $this->record('pos_bill', $billId, 'settled', $bill->propertyId, $bill->businessDate, __('Restaurant bill :no (:outlet)', ['no' => $bill->billNo, 'outlet' => $bill->outletName]), $bill->billNo,
            array_map(fn (int $account): array => ['account_id' => $account, 'debit' => $byAccount[$account]], array_keys($byAccount)));
    }

    /**
     * A settled bill voided: its entry is reversed whole.
     */
    public function restaurantBillVoided(int $billId): ?JournalEntry
    {
        $entry = JournalEntry::query()->where('source_type', 'pos_bill')->where('source_id', $billId)->where('source_event', 'settled')
            ->where('status', JournalStatus::Posted->value)->first();
        $bill = $this->restaurant->bill($billId);

        if (! $entry instanceof JournalEntry || ! $bill instanceof BillFact) {
            return null;
        }

        [$date] = $this->postingDate($bill->businessDate);

        return $this->reverse->handle($entry, $date, null, __('Bill :no voided', ['no' => $bill->billNo]));
    }

    /**
     * A closed POS session's cash difference: short is an expense, over is income.
     */
    public function sessionVariance(int $sessionId): ?JournalEntry
    {
        $session = $this->restaurant->session($sessionId);
        $variance = $session instanceof SessionFact ? BigDecimal::of($session->variance) : BigDecimal::zero();

        if (! $session instanceof SessionFact || $variance->isZero()) {
            return null;
        }

        $cash = $this->accounts->method(PaymentMethod::Cash);
        $other = $this->accounts->fixed($variance->isNegative() ? PostingKey::CashShort : PostingKey::CashOver);

        return $this->record('pos_session', $sessionId, 'variance', $session->propertyId, $session->businessDate,
            $variance->isNegative() ? __('Cash short of :outlet session', ['outlet' => $session->outletName]) : __('Cash over of :outlet session', ['outlet' => $session->outletName]), null, [
                $this->line($variance->isNegative() ? $other : $cash, (string) $variance->abs()->toScale(2), $session->propertyId),
                $this->line($variance->isNegative() ? $cash : $other, '-'.$variance->abs()->toScale(2), $session->propertyId),
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
