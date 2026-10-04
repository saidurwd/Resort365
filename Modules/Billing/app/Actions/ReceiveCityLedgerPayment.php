<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\Enums\CityLedgerStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Events\PaymentReceived;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\Payment;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * A company pays what it owes on the city ledger (one entry at a time, part payments allowed).
 */
class ReceiveCityLedgerPayment extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @throws PaymentNotAllowed
     */
    public function handle(CityLedgerEntry $entry, string $amount, PaymentMethod $method, ?string $reference = null, ?int $userId = null): Payment
    {
        return $this->transaction(function () use ($entry, $amount, $method, $reference, $userId): Payment {
            $locked = CityLedgerEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $value = BigDecimal::of($amount)->toScale(2);
            $open = BigDecimal::of($locked->open());

            if (! $value->isPositive() || $value->isGreaterThan($open)) {
                throw new PaymentNotAllowed(__('The amount must be more than zero and at most :open.', ['open' => (string) $open]));
            }

            $payment = Payment::query()->create([
                'property_id' => $locked->property_id,
                'receipt_no' => $this->numbers->next('payment', $locked->property_id),
                'payment_type' => PaymentType::Payment,
                'method' => $method,
                'amount' => (string) $value,
                'currency_code' => $this->properties->find($locked->property_id)->currencyCode ?? 'BDT',
                'exchange_rate' => '1',
                'base_amount' => (string) $value,
                'reference' => $reference,
                'city_ledger_entry_id' => $locked->id,
                'status' => PaymentStatus::Succeeded,
                'received_by' => $userId,
                'received_at' => now(),
            ]);

            $paid = BigDecimal::of($locked->paid)->plus($value)->toScale(2);
            $locked->forceFill(['paid' => (string) $paid, 'status' => $open->minus($value)->isZero() ? CityLedgerStatus::Paid : CityLedgerStatus::Open])->save();

            PaymentReceived::dispatch($payment->tenant_id, $payment->id, $payment->property_id, $payment->receipt_no, $payment->amount, $payment->currency_code, receivedBy: $userId);

            return $payment;
        });
    }
}
