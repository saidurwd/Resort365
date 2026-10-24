<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Billing\Actions\SendPaymentLink;
use Modules\Billing\Enums\OnlinePaymentPurpose;
use Modules\Billing\Exceptions\PaymentNotAllowed;

/**
 * The booking's Payments tab → "Send payment link" (Step 8.3): emails the guest a link to pay the deposit or the balance online.
 */
class PaymentLinkController extends Controller
{
    public function store(Request $request, int $reservation, SendPaymentLink $send): RedirectResponse
    {
        $validated = $request->validate(['purpose' => ['required', 'in:deposit,balance']]);
        $back = route('reservation.bookings.show', ['reservation' => $reservation]).'#payments';

        try {
            $link = $send->handle($reservation, OnlinePaymentPurpose::from((string) $validated['purpose']));
        } catch (PaymentNotAllowed $refused) {
            return redirect()->to($back)->with('error', $refused->getMessage());
        }

        return redirect()->to($back)->with('success', __('Payment link emailed to the guest.'))->with('payment_link', $link);
    }
}
