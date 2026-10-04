<?php

namespace Modules\Billing\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Actions\RecordPayment;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Http\Requests\RecordPaymentRequest;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\ReceiptData;

/**
 * Take a payment for a reservation (from its Payments tab) and download its receipt.
 */
class PaymentController extends Controller
{
    public function store(RecordPaymentRequest $request, RecordPayment $record): RedirectResponse
    {
        $back = $request->returnTo() ?? route('reservation.bookings.show', (int) $request->validated('reservation_id')).'#payments';

        try {
            $payment = $record->handle($request->payment());
        } catch (PaymentNotAllowed $exception) {
            return redirect()->to($back)->withInput()->withErrors(['amount' => $exception->getMessage()], 'payment');
        }

        return redirect()->to($back)->with('success', __('Payment :receipt received.', ['receipt' => $payment->receipt_no]));
    }

    public function receipt(Payment $payment, ReceiptData $data): Response
    {
        Gate::authorize('view', $payment);

        return Pdf::loadView('billing::payments.receipt', $data->for($payment))->setPaper('a5')->download($payment->receipt_no.'.pdf');
    }
}
