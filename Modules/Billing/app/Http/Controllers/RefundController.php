<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Billing\Actions\RequestRefund;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Http\Requests\RefundRequest;

/**
 * Pay money back (Payments tab, check-out screen).
 */
class RefundController extends Controller
{
    public function store(RefundRequest $request, RequestRefund $refund): RedirectResponse
    {
        $back = $request->returnTo() ?? route('reservation.bookings.show', (int) $request->validated('reservation_id')).'#payments';

        try {
            ['payment' => $payment, 'approval' => $approval] = $refund->handle($request->refund());
        } catch (PaymentNotAllowed $exception) {
            return redirect()->to($back)->with('error', $exception->getMessage());
        }

        if ($approval !== null) {
            return redirect()->to($back)->with('success', __('The refund of :amount needs approval (:step) before it is paid.', ['amount' => number_format((float) $approval->amount, 2), 'step' => (string) $approval->currentStep]));
        }

        return redirect()->to($back)->with('success', __('Refund :receipt paid (:amount).', ['receipt' => $payment?->receipt_no, 'amount' => number_format((float) $payment?->amount, 2)]));
    }
}
