<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\Contracts\CityLedgerAccounts;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Events\RestaurantBillVoided;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosPayment;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Services\ManagerApprovals;

/**
 * A manager's void of a settled bill (ARCHITECTURE §5.10.13 rule 2): only on the bill's business date,
 * with a reason, and restaurant.bill.void or a manager's PIN (`bill.void`). Every payment is refunded in
 * this session (a negative payment pointing at it), room charges are voided on the folio and account
 * charges taken off the company's account; the bill is voided and announced
 * (RestaurantBillVoided); when no bill of the order is left settled, the order is voided.
 */
class VoidBill extends Action
{
    public const string APPROVAL = 'bill.void';

    public function __construct(
        private readonly ManagerApprovals $approvals,
        private readonly PropertyDirectory $properties,
        private readonly FolioPostingContract $folios,
        private readonly CityLedgerAccounts $accounts,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(PosBill $bill, string $reason, bool $foodPrepared, PosSession $session, int $userId, bool $mayVoid, ?int $approvalId = null): PosBill
    {
        return $this->transaction(function () use ($bill, $reason, $foodPrepared, $session, $userId, $mayVoid, $approvalId): PosBill {
            $locked = PosBill::query()->lockForUpdate()->findOrFail($bill->id);

            if ($locked->status !== BillStatus::Settled) {
                throw new PosNotAllowed(__('Only a settled bill can be voided.'));
            }

            if ($locked->business_date->toDateString() !== ($this->properties->find($locked->property_id)->businessDate ?? null)) {
                throw new PosNotAllowed(__('Bill :no belongs to an earlier business date: give a refund instead.', ['no' => $locked->bill_no]));
            }

            if (trim($reason) === '') {
                throw new PosNotAllowed(__('Say why the bill is voided.'));
            }

            if (! $session->isOpen() || $session->outlet_id !== $locked->outlet_id) {
                throw new PosNotAllowed(__('Open a cash session on this terminal to refund the payments.'));
            }

            $approval = $mayVoid ? null : ($approvalId !== null
                ? $this->approvals->consume($approvalId, self::APPROVAL, $userId, 'pos_bill', $locked->id)
                : throw PosNotAllowed::needsApproval(__('Voiding a settled bill needs a manager\'s approval.'), self::APPROVAL));

            foreach (PosPayment::query()->where('pos_bill_id', $locked->id)->whereNull('refund_of_id')->get() as $payment) {
                $this->takeBack($payment, $locked, $userId);
                PosPayment::query()->create([
                    'property_id' => $locked->property_id, 'outlet_id' => $locked->outlet_id, 'pos_bill_id' => $locked->id, 'pos_session_id' => $session->id,
                    'business_date' => $session->business_date, 'method' => $payment->method, 'amount' => (string) BigDecimal::of($payment->amount)->negated(),
                    'tip' => (string) BigDecimal::of($payment->tip)->negated(), 'reference' => $payment->reference, 'refund_of_id' => $payment->id, 'created_by' => $userId,
                    'reservation_id' => $payment->reservation_id, 'company_id' => $payment->company_id, 'charged_to' => $payment->charged_to,
                ]);
            }

            $locked->forceFill([
                'status' => BillStatus::Voided, 'voided_at' => now(), 'voided_by' => $userId, 'void_reason' => mb_substr(trim($reason), 0, 300), 'manager_approval_id' => $approval?->id,
            ])->save();

            RestaurantBillVoided::dispatch($locked->tenant_id, $locked->property_id, $locked->outlet_id, $locked->id, $locked->pos_order_id,
                $locked->business_date->toDateString(), (string) $locked->grand_total, $foodPrepared, $locked->void_reason ?? '');

            $order = PosOrder::query()->lockForUpdate()->findOrFail($locked->pos_order_id);

            if (! PosBill::query()->where('pos_order_id', $order->id)->where('status', BillStatus::Settled->value)->exists()) {
                $order->forceFill(['status' => OrderStatus::Voided])->save();
            }

            return $locked;
        });
    }

    /**
     * A room charge is voided on the guest's folio; a city-ledger charge is taken off the company's
     * account. Refused when that is no longer possible (the guest checked out, the account was paid).
     *
     * @throws PosNotAllowed
     */
    private function takeBack(PosPayment $payment, PosBill $bill, int $userId): void
    {
        try {
            if ($payment->folio_line_id !== null) {
                $this->folios->reverseCharge($payment->folio_line_id, __('Restaurant bill :no voided', ['no' => $bill->bill_no]), $userId);
            }

            if ($payment->city_ledger_entry_id !== null) {
                $this->accounts->cancel($payment->city_ledger_entry_id, __('restaurant bill :no voided', ['no' => $bill->bill_no]));
            }
        } catch (ChargeRejected $exception) {
            throw new PosNotAllowed(__('Bill :no cannot be voided: :reason Ask the front desk for a credit note instead.', ['no' => $bill->bill_no, 'reason' => $exception->getMessage()]));
        }
    }
}
