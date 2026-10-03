<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\DepositConfirmation;
use Modules\Reservation\Services\ReservationBalance;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Renegotiates a booking's deposit percent (ARCHITECTURE §6.5 rule 2). Outside the deposit
 * policy's limits (e.g. 0% to waive it) needs reservation.deposit.override, and the user who
 * allowed it is kept. A tentative booking whose payments now cover the deposit is confirmed.
 */
class ChangeDeposit extends Action
{
    public function __construct(
        private readonly RateLookup $rates,
        private readonly ReservationBalance $balance,
        private readonly DepositConfirmation $confirmation,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @throws DepositBelowMinimum|ReservationNotChangeable
     */
    public function handle(Reservation $reservation, string $percent, bool $allowOverride = false, ?int $userId = null): Reservation
    {
        $withinPolicy = $this->rates->depositAllows($reservation->rate_plan_id, $percent);

        if (! $withinPolicy && ! $allowOverride) {
            throw new DepositBelowMinimum(__('A deposit of :percent% is outside the deposit policy. A manager can allow it.', ['percent' => $percent]));
        }

        return $this->transaction(function () use ($reservation, $percent, $withinPolicy, $userId): Reservation {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if (! $locked->isChangeable()) {
                throw new ReservationNotChangeable(__('Only tentative and confirmed bookings can be changed.'));
            }

            $before = [$locked->deposit_percent, $locked->deposit_required];
            $amount = $this->balance->deposit($locked->grand_total, $percent);

            $locked->forceFill([
                'deposit_percent' => $percent,
                'deposit_required' => $amount,
                'deposit_override_by' => $withinPolicy ? null : $userId,
                'payment_status' => $this->balance->status($locked->grand_total, $locked->amount_paid, $amount),
            ])->save();

            $this->logger->log($locked, ReservationLogAction::DepositChanged, __('Deposit changed from :old% (:oldAmount) to :new% (:newAmount).', [
                'old' => $before[0], 'oldAmount' => $before[1], 'new' => $locked->deposit_percent, 'newAmount' => $amount,
            ]).($withinPolicy ? '' : ' '.__('Allowed outside the deposit policy.')), [
                'deposit_percent' => [$before[0], $locked->deposit_percent], 'deposit_required' => [$before[1], $amount],
            ], $userId);

            $this->confirmation->confirmIfMet($locked, $userId);

            return $locked;
        }, attempts: 3);
    }
}
