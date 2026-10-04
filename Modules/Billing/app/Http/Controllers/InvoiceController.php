<?php

namespace Modules\Billing\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Actions\IssueCreditNote;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Http\Requests\CreditNoteRequest;
use Modules\Billing\Models\CreditNote;
use Modules\Billing\Models\Invoice;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Invoice and credit-note PDFs, and issuing a credit note.
 */
class InvoiceController extends Controller
{
    public function pdf(Invoice $invoice, PropertyDirectory $properties): Response
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['lines', 'creditNotes']);

        return Pdf::loadView('billing::invoices.pdf', ['invoice' => $invoice, 'property' => $properties->find($invoice->property_id)])
            ->setPaper('a4')->download($invoice->invoice_no.'.pdf');
    }

    public function credit(CreditNoteRequest $request, Invoice $invoice, IssueCreditNote $issue): RedirectResponse
    {
        Gate::authorize('credit', $invoice);
        $back = route('reservation.bookings.show', (int) $invoice->reservation_id).'#folios';

        try {
            $note = $issue->handle($invoice, (string) $request->validated('amount'), (string) $request->validated('reason'), $request->user() !== null ? (int) $request->user()->getAuthIdentifier() : null);
        } catch (PaymentNotAllowed $exception) {
            return redirect()->to($back)->with('error', $exception->getMessage());
        }

        return redirect()->to($back)->with('success', __('Credit note :no issued.', ['no' => $note->credit_note_no]));
    }

    public function creditNotePdf(CreditNote $creditNote, PropertyDirectory $properties): Response
    {
        $creditNote->load('invoice');
        Gate::authorize('view', $creditNote->invoice);

        return Pdf::loadView('billing::invoices.credit-note-pdf', ['note' => $creditNote, 'invoice' => $creditNote->invoice, 'property' => $properties->find($creditNote->property_id)])
            ->setPaper('a4')->download($creditNote->credit_note_no.'.pdf');
    }
}
