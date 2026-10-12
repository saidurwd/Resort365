<?php

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Procurement\Actions\ReturnToVendor;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Http\Controllers\Concerns\ProcurementScreen;
use Modules\Procurement\Http\Requests\ReturnRequest;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseReturn;
use Modules\Procurement\Services\ItemLabels;

/**
 * Procurement → Returns: send accepted goods back to the vendor and issue a debit note.
 */
class ReturnController extends Controller
{
    use ProcurementScreen;

    public function index(): View
    {
        Gate::authorize('viewAny', PurchaseReturn::class);

        return view('procurement::returns.index', ['returns' => PurchaseReturn::query()->with(['vendor', 'receipt'])->where('property_id', $this->propertyId())->orderByDesc('id')->limit(100)->get(), 'subtitle' => $this->propertyName()]);
    }

    public function create(GoodsReceipt $receipt, ItemLabels $labels): View
    {
        Gate::authorize('create', PurchaseReturn::class);
        $receipt->load(['vendor', 'lines']);

        return view('procurement::returns.create', ['receipt' => $receipt, 'items' => $labels->for($receipt->lines->pluck('item_id')), 'subtitle' => $this->propertyName()]);
    }

    public function store(ReturnRequest $request, GoodsReceipt $receipt, ReturnToVendor $return): RedirectResponse
    {
        Gate::authorize('create', PurchaseReturn::class);

        try {
            $result = $return->handle($receipt, $request->lines(), (string) $request->validated('reason'), $this->userId(), $request->filled('returned_on') ? (string) $request->validated('returned_on') : null, $request->filled('notes') ? (string) $request->validated('notes') : null);
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.returns.show', $result)->with('success', __('Return :no recorded with debit note :dn.', ['no' => $result->return_no, 'dn' => $result->debit_note_no]));
    }

    public function show(PurchaseReturn $return, ItemLabels $labels, AuditTrail $audit): View
    {
        Gate::authorize('view', $return);
        $return->load(['vendor', 'receipt', 'lines']);

        return view('procurement::returns.show', ['return' => $return, 'items' => $labels->for($return->lines->pluck('item_id')), 'trail' => $audit->for($return), 'subtitle' => $this->propertyName()]);
    }
}
