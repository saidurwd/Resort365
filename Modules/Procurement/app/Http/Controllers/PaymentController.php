<?php

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Procurement\Actions\AllocateVendorPayment;
use Modules\Procurement\Actions\RecordVendorPayment;
use Modules\Procurement\DTOs\NewVendorPayment;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Enums\VendorPaymentMethod;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Http\Controllers\Concerns\ProcurementScreen;
use Modules\Procurement\Http\Requests\AllocateRequest;
use Modules\Procurement\Http\Requests\PaymentRequest;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorPayment;
use Modules\Procurement\Services\Lookups;

/**
 * Procurement → Vendor payments: pay a vendor against approved bills, or in advance, and apply an advance later.
 */
class PaymentController extends Controller
{
    use ProcurementScreen;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', VendorPayment::class);

        return view('procurement::payments.index', [
            'payments' => VendorPayment::query()->with('vendor')->where('property_id', $this->propertyId())->orderByDesc('id')->limit(100)->get(),
            'canCreate' => $request->user()?->can('procurement.payment.create') ?? false, 'subtitle' => $this->propertyName(),
        ]);
    }

    public function create(Request $request, Lookups $lookups): View
    {
        Gate::authorize('create', VendorPayment::class);
        $vendorId = $request->filled('vendor') ? (int) $request->query('vendor') : null;

        return view('procurement::payments.create', [
            'vendors' => $lookups->vendors(false), 'vendorId' => $vendorId, 'bills' => $vendorId !== null ? $this->openBills($vendorId) : collect(), 'billId' => $request->filled('bill') ? (int) $request->query('bill') : null,
            'methods' => collect(VendorPaymentMethod::cases())->mapWithKeys(fn (VendorPaymentMethod $method): array => [$method->value => $method->label()])->all(), 'subtitle' => $this->propertyName(),
        ]);
    }

    public function store(PaymentRequest $request, RecordVendorPayment $record): RedirectResponse
    {
        Gate::authorize('create', VendorPayment::class);

        try {
            $payment = $record->handle(new NewVendorPayment(
                $this->propertyId(), (int) $request->validated('vendor_id'), VendorPaymentMethod::from((string) $request->validated('method')), (string) $request->validated('amount'), $this->userId(), $request->allocations(),
                $request->filled('paid_on') ? (string) $request->validated('paid_on') : null, $request->filled('reference') ? (string) $request->validated('reference') : null, $request->filled('notes') ? (string) $request->validated('notes') : null,
            ));
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.payments.show', $payment)->with('success', __('Payment :no recorded.', ['no' => $payment->payment_no]));
    }

    public function show(Request $request, VendorPayment $payment, AuditTrail $audit): View
    {
        Gate::authorize('view', $payment);
        $payment->load(['vendor', 'allocations.bill']);

        return view('procurement::payments.show', [
            'payment' => $payment, 'bills' => $this->openBills($payment->vendor_id), 'trail' => $audit->for($payment),
            'canAllocate' => bccomp((string) $payment->amount, (string) $payment->allocated_amount, 2) > 0 && ($request->user()?->can('procurement.payment.create') ?? false), 'subtitle' => $this->propertyName(),
        ]);
    }

    public function allocate(AllocateRequest $request, VendorPayment $payment, AllocateVendorPayment $allocate): RedirectResponse
    {
        Gate::authorize('create', VendorPayment::class);

        try {
            $allocate->handle($payment, $request->allocations());
        } catch (ProcurementRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('procurement.payments.show', $payment)->with('success', __('Allocated.'));
    }

    /**
     * @return Collection<int, VendorBill>
     */
    private function openBills(int $vendorId)
    {
        return VendorBill::query()->where('vendor_id', $vendorId)->whereIn('status', [VendorBillStatus::Approved->value, VendorBillStatus::Partial->value])->orderBy('due_on')->get();
    }
}
