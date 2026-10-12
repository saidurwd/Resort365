<?php

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Procurement\Actions\AwardRfq;
use Modules\Procurement\Actions\CancelRfq;
use Modules\Procurement\Actions\CreateRfq;
use Modules\Procurement\Actions\RecordQuotation;
use Modules\Procurement\Actions\SendRfq;
use Modules\Procurement\Enums\RfqStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Http\Controllers\Concerns\ProcurementScreen;
use Modules\Procurement\Http\Requests\AwardRequest;
use Modules\Procurement\Http\Requests\QuotationRequest;
use Modules\Procurement\Http\Requests\RfqRequest;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\VendorQuotation;
use Modules\Procurement\Services\ItemLabels;
use Modules\Procurement\Services\Lookups;
use Modules\Procurement\Services\QuotationComparer;

/**
 * Procurement → RFQs: ask several vendors for prices, enter their quotations, compare them and award the winner.
 */
class RfqController extends Controller
{
    use ProcurementScreen;

    public function index(): View
    {
        Gate::authorize('viewAny', Rfq::class);

        return view('procurement::rfqs.index', [
            'rfqs' => Rfq::query()->with('requisition')->withCount(['vendors', 'quotations'])->where('property_id', $this->propertyId())->orderByDesc('id')->limit(100)->get(), 'subtitle' => $this->propertyName(),
        ]);
    }

    public function store(RfqRequest $request, CreateRfq $create): RedirectResponse
    {
        Gate::authorize('create', Rfq::class);
        $requisition = PurchaseRequisition::query()->findOrFail((int) $request->validated('purchase_requisition_id'));

        try {
            $rfq = $create->handle($requisition, array_map(intval(...), (array) $request->validated('vendors')), $this->userId(),
                $request->filled('due_on') ? (string) $request->validated('due_on') : null, $request->filled('notes') ? (string) $request->validated('notes') : null);
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.rfqs.show', $rfq)->with('success', __('RFQ :no prepared: send it to the vendors.', ['no' => $rfq->rfq_no]));
    }

    public function show(Request $request, Rfq $rfq, QuotationComparer $comparer, ItemLabels $labels, Lookups $lookups, AuditTrail $audit): View
    {
        Gate::authorize('view', $rfq);
        $rfq->load(['requisition.lines', 'vendors.vendor', 'quotations.vendor', 'quotations.lines']);
        $asked = $rfq->requisition->lines->mapWithKeys(fn ($line): array => [$line->item_id => (string) $line->quantity])->all();
        $quotes = $rfq->quotations->mapWithKeys(fn (VendorQuotation $quotation): array => [$quotation->id => $quotation->lines->mapWithKeys(fn ($line): array => [$line->item_id => (string) $line->unit_price])->all()])->all();
        $canManage = $request->user()?->can('procurement.rfq.manage') ?? false;

        return view('procurement::rfqs.show', [
            'rfq' => $rfq, 'asked' => $asked, 'quotes' => $quotes, 'comparison' => $comparer->compare($asked, $quotes), 'items' => $labels->for(array_keys($asked)),
            'stores' => $lookups->stores(), 'canManage' => $canManage, 'open' => $rfq->status === RfqStatus::Sent, 'trail' => $audit->for($rfq), 'subtitle' => $this->propertyName(),
        ]);
    }

    public function send(Rfq $rfq, SendRfq $send): RedirectResponse
    {
        Gate::authorize('update', $rfq);

        try {
            $result = $send->handle($rfq);
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $message = __('RFQ sent to the vendors.');

        if ($result['skipped'] !== []) {
            $message .= ' '.__('No email address for: :names.', ['names' => implode(', ', $result['skipped'])]);
        }

        return to_route('procurement.rfqs.show', $rfq)->with('success', $message);
    }

    public function quote(QuotationRequest $request, Rfq $rfq, RecordQuotation $record): RedirectResponse
    {
        Gate::authorize('update', $rfq);

        try {
            $record->handle($rfq, (int) $request->validated('vendor_id'), $request->prices(), $request->filled('reference') ? (string) $request->validated('reference') : null,
                $request->filled('quoted_on') ? (string) $request->validated('quoted_on') : null, $request->filled('valid_until') ? (string) $request->validated('valid_until') : null,
                $request->filled('notes') ? (string) $request->validated('notes') : null);
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.rfqs.show', $rfq)->with('success', __('Quotation saved.'));
    }

    public function award(AwardRequest $request, Rfq $rfq, AwardRfq $award): RedirectResponse
    {
        Gate::authorize('update', $rfq);
        $quotation = VendorQuotation::query()->findOrFail((int) $request->validated('quotation_id'));

        try {
            $order = $award->handle($rfq, $quotation, (int) $request->validated('store_id'), $this->userId(), $request->filled('expected_on') ? (string) $request->validated('expected_on') : null);
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('procurement.orders.show', $order)->with('success', __('Quotation selected: review order :no and send it for approval.', ['no' => $order->order_no]));
    }

    public function cancel(Rfq $rfq, CancelRfq $cancel): RedirectResponse
    {
        Gate::authorize('update', $rfq);

        try {
            $cancel->handle($rfq);
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('procurement.rfqs.index')->with('success', __('RFQ cancelled.'));
    }
}
