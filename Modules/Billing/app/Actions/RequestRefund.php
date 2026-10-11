<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\DTOs\NewRefund;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\Payment;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\DTOs\ApprovalSummary;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Reservation\Contracts\ReservationLookup;

/**
 * Asks to pay money back (Step 5.1). The tenant's `refund` approval workflow decides: a refund below every step's
 * amount is paid at once as before; a bigger one waits for the approvers, and `RefundApprovalHandler` pays it when the
 * last one approves. A refund is checked now, and again when approved.
 */
class RequestRefund extends Action
{
    public function __construct(
        private readonly RefundPayment $refund,
        private readonly ApprovalEngine $approvals,
        private readonly ReservationLookup $reservations,
    ) {}

    /**
     * @return array{payment: Payment|null, approval: ApprovalSummary|null}
     *
     * @throws PaymentNotAllowed
     */
    public function handle(NewRefund $data): array
    {
        $this->refund->check($data, $this->pending($data));
        $reservation = $this->reservations->find($data->reservationId) ?? throw new PaymentNotAllowed(__('Unknown reservation.'));
        $approval = $this->approvals->submit(
            'refund', (string) BigDecimal::of($data->amount)->toScale(2), __('Refund :kind of booking :code', ['kind' => mb_strtolower($data->kind->label()), 'code' => $reservation->code]), $data->issuedBy ?? 0,
            ['reservation_id' => $data->reservationId, 'kind' => $data->kind->value, 'method' => $data->method->value, 'amount' => (string) BigDecimal::of($data->amount)->toScale(2), 'reason' => $data->reason, 'source_id' => $data->sourceId, 'reference' => $data->reference],
            $reservation->propertyId, 'reservation', $data->reservationId,
        );

        return $approval instanceof ApprovalSummary ? ['payment' => null, 'approval' => $approval] : ['payment' => $this->refund->handle($data), 'approval' => null];
    }

    /**
     * Refunds of the same booking, kind and source already waiting: they count against what can still be refunded.
     */
    public function pending(NewRefund $data): string
    {
        $sum = BigDecimal::zero();

        foreach ($this->approvals->forSubject('refund', 'reservation', $data->reservationId) as $summary) {
            if ($summary->status === ApprovalStatus::Pending && ($summary->payload['kind'] ?? null) === $data->kind->value && ($summary->payload['source_id'] ?? null) === $data->sourceId) {
                $sum = $sum->plus($summary->payload['amount'] ?? '0');
            }
        }

        return (string) $sum->toScale(2);
    }
}
