<?php

namespace Modules\Reservation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Reservation\Actions\ChangeDeposit;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Reservation\Http\Requests\ChangeDepositRequest;
use Modules\Reservation\Models\Reservation;

class ReservationDepositController extends Controller
{
    public function update(ChangeDepositRequest $request, Reservation $reservation, ChangeDeposit $change): RedirectResponse
    {
        $user = $request->user();

        try {
            $change->handle($reservation, (string) $request->validated('deposit_percent'), $user?->can('reservation.deposit.override') ?? false, $user?->getAuthIdentifier());
        } catch (DepositBelowMinimum|ReservationNotChangeable $exception) {
            return back()->withErrors(['deposit_percent' => $exception->getMessage()]);
        }

        return back()->with('success', __('Deposit changed.'));
    }
}
