<?php

namespace Modules\Billing\Services;

use Modules\Billing\Actions\RefundPayment;
use Modules\Billing\DTOs\NewRefund;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\RefundKind;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Core\Contracts\ApprovalHandler;
use Modules\Core\DTOs\ApprovalDecision;
use Modules\Core\Exceptions\ApprovalExecutionFailed;

/**
 * Pays an approved refund (Step 5.1), as the person who asked for it. If the books changed meanwhile and it can no
 * longer be paid, the approval is not recorded and the approver may reject the request.
 */
class RefundApprovalHandler implements ApprovalHandler
{
    public function __construct(private readonly RefundPayment $refund) {}

    public function approved(ApprovalDecision $decision): void
    {
        $p = $decision->payload;

        try {
            $this->refund->handle(new NewRefund(
                (int) $p['reservation_id'], RefundKind::from((string) $p['kind']), PaymentMethod::from((string) $p['method']), (string) $p['amount'], (string) $p['reason'],
                isset($p['source_id']) ? (int) $p['source_id'] : null, isset($p['reference']) ? (string) $p['reference'] : null, $decision->requestedBy ?: null,
            ));
        } catch (PaymentNotAllowed $exception) {
            throw new ApprovalExecutionFailed(__('The refund can no longer be paid: :reason Reject the request instead.', ['reason' => $exception->getMessage()]), $exception->getCode(), $exception);
        }
    }

    public function rejected(ApprovalDecision $decision): void {}
}
