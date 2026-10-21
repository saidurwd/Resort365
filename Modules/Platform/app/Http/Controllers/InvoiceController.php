<?php

namespace Modules\Platform\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Platform\Actions\RecordPlatformPayment;
use Modules\Platform\Actions\VoidPlatformInvoice;
use Modules\Platform\Enums\PlatformInvoiceStatus;
use Modules\Platform\Enums\PlatformPaymentMethod;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Http\Requests\PlatformPaymentRequest;
use Modules\Platform\Http\Requests\ReasonRequest;
use Modules\Platform\Models\PlatformInvoice;
use Modules\Platform\Services\PlatformInvoiceDocuments;

/**
 * Platform console → Invoices (Step 7.4): every platform invoice with its tenant, filtered by status; the PDF; recording a payment;
 * voiding an invoice nothing was paid on.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $status = PlatformInvoiceStatus::tryFrom((string) $request->query('status', ''));
        $overdue = $request->boolean('overdue');
        $invoices = PlatformInvoice::query()->with('tenant')
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->when($overdue, fn ($query) => $query->where('status', PlatformInvoiceStatus::Open->value)->whereDate('due_on', '<', today()))
            ->orderByDesc('issued_on')->orderByDesc('id')->paginate(50)->withQueryString();

        return view('platform::invoices.index', ['invoices' => $invoices, 'status' => $status, 'overdue' => $overdue, 'statuses' => PlatformInvoiceStatus::options()]);
    }

    public function show(PlatformInvoice $invoice): View
    {
        return view('platform::invoices.show', ['invoice' => $invoice->load(['tenant', 'payments']), 'methods' => PlatformPaymentMethod::options()]);
    }

    public function pdf(PlatformInvoice $invoice, PlatformInvoiceDocuments $documents): Response
    {
        return response($documents->pdf($invoice), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$invoice->number.'.pdf"']);
    }

    public function pay(PlatformPaymentRequest $request, PlatformInvoice $invoice, RecordPlatformPayment $record): RedirectResponse
    {
        try {
            $record->handle($invoice, (string) $request->validated('amount'), PlatformPaymentMethod::from((string) $request->validated('method')), $request->validated('reference'),
                CarbonImmutable::parse((string) $request->validated('paid_on')), $request->user('platform'));
        } catch (PlatformRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('platform.invoices.show', $invoice)->with('success', __('Payment recorded.'));
    }

    public function void(ReasonRequest $request, PlatformInvoice $invoice, VoidPlatformInvoice $void): RedirectResponse
    {
        try {
            $void->handle($invoice, (string) $request->validated('reason'), $request->user('platform'));
        } catch (PlatformRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('platform.invoices.show', $invoice)->with('success', __('Invoice voided.'));
    }
}
