<?php

namespace Modules\Procurement\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Procurement\Actions\DeleteVendorContact;
use Modules\Procurement\Actions\DeleteVendorPrice;
use Modules\Procurement\Actions\SaveVendor;
use Modules\Procurement\Actions\SaveVendorContact;
use Modules\Procurement\Actions\SaveVendorPrice;
use Modules\Procurement\Enums\VendorStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Http\Requests\VendorContactRequest;
use Modules\Procurement\Http\Requests\VendorPriceRequest;
use Modules\Procurement\Http\Requests\VendorRequest;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorContact;
use Modules\Procurement\Models\VendorItem;
use Modules\Procurement\Services\ItemLabels;
use Modules\Procurement\Services\Lookups;
use Modules\Procurement\Services\VendorBalances;

/**
 * Procurement → Vendors: the supplier list, a vendor's profile with contacts, price list and documents.
 */
class VendorController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vendor::class);
        $status = VendorStatus::tryFrom((string) $request->query('status'));
        $term = trim((string) $request->query('q'));

        return view('procurement::vendors.index', [
            'vendors' => Vendor::query()->withCount('prices')->when($status instanceof VendorStatus, fn ($query) => $query->where('status', $status?->value))
                ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$term.'%')->orWhere('code', 'like', $term.'%')))->orderBy('name')->limit(200)->get(),
            'canManage' => $request->user()?->can('procurement.vendor.manage') ?? false, 'term' => $term, 'status' => $status?->value,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Vendor::class);

        return view('procurement::vendors.form', ['vendor' => null]);
    }

    public function store(VendorRequest $request, SaveVendor $save): RedirectResponse
    {
        Gate::authorize('create', Vendor::class);

        try {
            $vendor = $save->handle(null, $request->validated());
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.vendors.show', $vendor)->with('success', __('Vendor :code added.', ['code' => $vendor->code]));
    }

    public function edit(Vendor $vendor): View
    {
        Gate::authorize('update', $vendor);

        return view('procurement::vendors.form', ['vendor' => $vendor]);
    }

    public function update(VendorRequest $request, Vendor $vendor, SaveVendor $save): RedirectResponse
    {
        Gate::authorize('update', $vendor);

        try {
            $save->handle($vendor, $request->validated());
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.vendors.show', $vendor)->with('success', __('Vendor saved.'));
    }

    public function show(Request $request, Vendor $vendor, ItemLabels $labels, Lookups $lookups, AuditTrail $audit, VendorBalances $balances): View
    {
        Gate::authorize('view', $vendor);
        $vendor->load(['contacts', 'prices']);
        $today = CarbonImmutable::now()->toDateString();

        return view('procurement::vendors.show', [
            'vendor' => $vendor, 'items' => $labels->for($vendor->prices->pluck('item_id')), 'itemOptions' => $lookups->items(), 'today' => $today,
            'orders' => PurchaseOrder::query()->where('vendor_id', $vendor->id)->orderByDesc('id')->limit(10)->get(),
            'payable' => $balances->payable($vendor->id), 'advance' => $balances->advance($vendor->id),
            'canPay' => $request->user()?->can('procurement.payment.create') ?? false,
            'canManage' => $request->user()?->can('procurement.vendor.manage') ?? false, 'trail' => $audit->for($vendor),
        ]);
    }

    public function addContact(VendorContactRequest $request, Vendor $vendor, SaveVendorContact $save): RedirectResponse
    {
        Gate::authorize('update', $vendor);
        $save->handle($vendor, null, $request->validated());

        return to_route('procurement.vendors.show', $vendor)->with('success', __('Contact added.'));
    }

    public function removeContact(Vendor $vendor, VendorContact $contact, DeleteVendorContact $delete): RedirectResponse
    {
        Gate::authorize('update', $vendor);
        abort_unless($contact->vendor_id === $vendor->id, 404);
        $delete->handle($contact);

        return to_route('procurement.vendors.show', $vendor)->with('success', __('Contact removed.'));
    }

    public function addPrice(VendorPriceRequest $request, Vendor $vendor, SaveVendorPrice $save): RedirectResponse
    {
        Gate::authorize('update', $vendor);

        try {
            $save->handle($vendor, null, $request->validated());
        } catch (ProcurementRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('procurement.vendors.show', $vendor)->with('success', __('Price added.'));
    }

    public function removePrice(Vendor $vendor, VendorItem $entry, DeleteVendorPrice $delete): RedirectResponse
    {
        Gate::authorize('update', $vendor);
        abort_unless($entry->vendor_id === $vendor->id, 404);
        $delete->handle($entry);

        return to_route('procurement.vendors.show', $vendor)->with('success', __('Price removed.'));
    }
}
