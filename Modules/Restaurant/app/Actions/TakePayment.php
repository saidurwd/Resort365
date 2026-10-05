<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantStorage;
use Brick\Math\BigDecimal;
use Modules\Billing\Contracts\CityLedgerAccounts;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\DTOs\ChargeableStay;
use Modules\Billing\DTOs\CityLedgerCharge;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Core\Contracts\Settings;
use Modules\Restaurant\DTOs\ChargeTarget;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\PaymentMethod;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosPayment;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Services\BillSettlement;
use Modules\Restaurant\Services\TaxShare;

/**
 * Takes a payment on a printed bill in the terminal's open session (ARCHITECTURE §5.10.7, §5.10.11): an
 * amount towards what is still due (never more), a tip on top, and for cash what was tendered and the
 * change. Several payments may settle one bill (cash + card); the last one settles it.
 *
 * Charge to room (§5.10.8, AD-16): posted at once to the in-house guest's folio through Billing's
 * FolioPostingContract, inside this transaction, with the bill's own taxes (TaxShare) and
 * revenue_posted_by_source; refused (and nothing saved) when the guest is not in house, the folio is
 * closed, over its credit limit, or the booking takes no room charges. City ledger: billed to a
 * company's account (CityLedgerAccounts), within its credit limit. Neither takes a tip.
 */
class TakePayment extends Action
{
    public function __construct(
        private readonly BillSettlement $settlement,
        private readonly FolioPostingContract $folios,
        private readonly CityLedgerAccounts $accounts,
        private readonly TaxShare $taxShare,
        private readonly Settings $settings,
        private readonly TenantStorage $storage,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(PosBill $bill, PaymentMethod $method, string $amount, string $tip, ?string $tendered, ?string $reference, PosSession $session, int $userId,
        ?ChargeTarget $target = null): PosPayment
    {
        if (! in_array($method, PaymentMethod::tenders(), true)) {
            throw new PosNotAllowed(__(':method is not taken here.', ['method' => $method->label()]));
        }

        return $this->transaction(function () use ($bill, $method, $amount, $tip, $tendered, $reference, $session, $userId, $target): PosPayment {
            $locked = PosBill::query()->lockForUpdate()->with('outlet')->findOrFail($bill->id);
            $open = PosSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $open->isOpen() || $open->outlet_id !== $locked->outlet_id) {
                throw new PosNotAllowed(__('Open a cash session on this terminal to take payments.'));
            }

            if ($locked->status !== BillStatus::Printed) {
                throw new PosNotAllowed(__('Bill :no is :status.', ['no' => $locked->bill_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $amount = BigDecimal::of($amount)->toScale(2);
            $tip = BigDecimal::of($tip === '' ? '0' : $tip)->toScale(2);
            $due = BigDecimal::of($locked->grand_total)->minus($locked->paid_total);

            if ($amount->isNegativeOrZero() || $tip->isNegative()) {
                throw new PosNotAllowed(__('Enter the amount paid.'));
            }

            if ($amount->isGreaterThan($due)) {
                throw new PosNotAllowed(__('Only :due is still due on this bill; enter anything more as a tip.', ['due' => (string) $due]));
            }

            $change = BigDecimal::zero();
            $charged = [];

            if ($method === PaymentMethod::Cash) {
                $given = BigDecimal::of($tendered ?? (string) $amount->plus($tip));

                if ($given->isLessThan($amount->plus($tip))) {
                    throw new PosNotAllowed(__('The cash given is less than the payment and tip.'));
                }

                $change = $given->minus($amount)->minus($tip);
            } elseif (in_array($method, [PaymentMethod::RoomCharge, PaymentMethod::CityLedger], true)) {
                if ($tip->isPositive()) {
                    throw new PosNotAllowed(__('A tip cannot be charged to a room or an account; take it in cash or by card.'));
                }

                $charged = $method === PaymentMethod::RoomCharge
                    ? $this->chargeRoom($locked, (string) $amount, $target?->reservationId, $userId)
                    : $this->chargeAccount($locked, (string) $amount, $target?->companyId);
            } elseif (trim((string) $reference) === '' && $method !== PaymentMethod::Wallet) {
                throw new PosNotAllowed(__('Enter the :method reference (approval code or last digits).', ['method' => mb_strtolower($method->label())]));
            }

            $payment = PosPayment::query()->create([
                'property_id' => $locked->property_id, 'outlet_id' => $locked->outlet_id, 'pos_bill_id' => $locked->id, 'pos_session_id' => $open->id,
                'business_date' => $open->business_date, 'method' => $method, 'amount' => (string) $amount, 'tip' => (string) $tip,
                'tendered' => $method === PaymentMethod::Cash ? (string) $amount->plus($tip)->plus($change) : null, 'change_given' => (string) $change,
                'reference' => trim((string) $reference) ?: null, 'created_by' => $userId, ...$charged,
            ]);

            if ($method === PaymentMethod::RoomCharge && $target?->signature !== null) {
                $this->saveSignature($payment, $target->signature);
            }

            $locked->forceFill(['paid_total' => (string) BigDecimal::of($locked->paid_total)->plus($amount), 'tip_total' => (string) BigDecimal::of($locked->tip_total)->plus($tip)])->save();
            $this->settlement->settleIfPaid($locked, $userId);

            return $payment;
        });
    }

    /**
     * @return array<string, mixed> the payment's room-charge fields
     *
     * @throws PosNotAllowed
     */
    private function chargeRoom(PosBill $bill, string $amount, ?int $reservationId, int $userId): array
    {
        $stay = $reservationId === null ? null : collect($this->folios->chargeableStays($bill->property_id))->firstWhere('reservationId', $reservationId);

        if (! $stay instanceof ChargeableStay) {
            throw new PosNotAllowed(__('Choose a guest who is in house.'));
        }

        $code = $this->folios->chargeCodeId((string) $this->settings->get('restaurant.room_charge_code'));

        if ($code === null) {
            throw new PosNotAllowed(__('Set up the charge code :code (Setup → Charge codes) to charge rooms.', ['code' => $this->settings->get('restaurant.room_charge_code')]));
        }

        $share = $this->taxShare->of($bill->tax_breakdown ?? [], (string) $bill->grand_total, $amount);

        try {
            $posting = $this->folios->postCharge(new FolioCharge(
                reservationId: $stay->reservationId, chargeCodeId: $code, unitPrice: $share['net'],
                description: __(':outlet bill :no', ['outlet' => $bill->outlet->name, 'no' => $bill->bill_no]),
                referenceType: 'pos_bill', referenceId: $bill->id, revenuePostedBySource: true, postedBy: $userId, taxLines: $share['taxes'], outletCharge: true,
            ));
        } catch (ChargeRejected $exception) {
            throw new PosNotAllowed($exception->getMessage());
        }

        return [
            'reservation_id' => $stay->reservationId, 'folio_id' => $posting->folioId, 'folio_line_id' => $posting->lineId,
            'charged_to' => mb_substr(implode(', ', $stay->rooms).' · '.$stay->guestName.' · '.$stay->code, 0, 190),
        ];
    }

    /**
     * @return array<string, mixed> the payment's city-ledger fields
     *
     * @throws PosNotAllowed
     */
    private function chargeAccount(PosBill $bill, string $amount, ?int $companyId): array
    {
        if ($companyId === null) {
            throw new PosNotAllowed(__('Choose the company to bill.'));
        }

        try {
            $entry = $this->accounts->charge(new CityLedgerCharge($bill->property_id, $companyId, $amount,
                __(':outlet bill :no', ['outlet' => $bill->outlet->name, 'no' => $bill->bill_no]), 'pos_bill', $bill->id));
        } catch (ChargeRejected $exception) {
            throw new PosNotAllowed($exception->getMessage());
        }

        return ['company_id' => $companyId, 'city_ledger_entry_id' => $entry, 'charged_to' => $this->accounts->account($companyId)?->name];
    }

    /**
     * Keeps the guest's on-screen signature (a PNG data URL) with the payment, privately.
     */
    private function saveSignature(PosPayment $payment, string $signature): void
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $signature, $match) || strlen($match[1]) > 400_000) {
            return;
        }

        $path = 'restaurant/signatures/'.$payment->id.'.png';
        $this->storage->put($path, (string) base64_decode($match[1], true));
        $payment->forceFill(['signature_path' => $path])->save();
    }
}
