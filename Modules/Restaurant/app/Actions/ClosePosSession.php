<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use App\Support\Cash\CashCount;
use Brick\Math\BigDecimal;
use Modules\Core\Contracts\Settings;
use Modules\Restaurant\Enums\PosSessionStatus;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Services\ManagerApprovals;
use Modules\Restaurant\Services\SessionCash;

/**
 * Closes a POS session with the cash counted by denomination (ARCHITECTURE §5.10.11): not while it
 * has open bills; expected = float + cash received − cash refunded; a variance needs a reason, and
 * one above the property's restaurant.session_variance_limit also a manager's approval (PIN). The
 * session is then locked and its Z report can be printed.
 */
class ClosePosSession extends Action
{
    public const string APPROVAL = 'session.close-variance';

    public function __construct(
        private readonly CashCount $count,
        private readonly SessionCash $cash,
        private readonly ManagerApprovals $approvals,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<int|string, int|string|null>  $denominations  note or coin => how many
     *
     * @throws PosNotAllowed
     */
    public function handle(PosSession $session, array $denominations, ?string $reason, int $userId, ?int $approvalId = null): PosSession
    {
        return $this->transaction(function () use ($session, $denominations, $reason, $userId, $approvalId): PosSession {
            $locked = PosSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isOpen()) {
                throw new PosNotAllowed(__('This session is already closed.'));
            }

            $openBills = $this->cash->openBills($locked);

            if ($openBills > 0) {
                throw new PosNotAllowed(trans_choice('Settle or move the :count open bill first.|Settle or move the :count open bills first.', $openBills));
            }

            [$received, $refunded] = $this->cash->cash($locked);
            $counted = $this->count->counted($denominations);
            $expected = $this->count->expected($locked->opening_float, $received, $refunded);
            $variance = $this->count->variance($counted, $expected);
            $reason = trim((string) $reason);
            $over = BigDecimal::of($variance)->abs()->isGreaterThan((string) $this->settings->get('restaurant.session_variance_limit', $locked->property_id));
            $approval = null;

            if (! BigDecimal::of($variance)->isZero() && $reason === '') {
                throw new PosNotAllowed(BigDecimal::of($variance)->isNegative()
                    ? __('The cash is :amount short: give a reason.', ['amount' => (string) BigDecimal::of($variance)->abs()])
                    : __('The cash is :amount over: give a reason.', ['amount' => $variance]));
            }

            if ($over) {
                if ($approvalId === null) {
                    throw new PosNotAllowed(__('The difference of :amount needs a manager\'s approval.', ['amount' => $variance]));
                }

                $approval = $this->approvals->consume($approvalId, self::APPROVAL, $userId, 'pos_session', $locked->id);
            }

            $locked->forceFill([
                'closed_at' => now(), 'closed_by' => $userId, 'cash_received' => $received, 'cash_refunded' => $refunded, 'expected_cash' => $expected,
                'counted_cash' => $counted, 'cash_variance' => $variance, 'variance_reason' => $reason !== '' ? $reason : null,
                'denominations' => array_filter(array_map(fn (int|string|null $count): int => (int) $count, $denominations)), 'manager_approval_id' => $approval?->id,
                'status' => PosSessionStatus::Closed, 'open_terminal_id' => null,
            ])->save();

            return $locked;
        });
    }
}
