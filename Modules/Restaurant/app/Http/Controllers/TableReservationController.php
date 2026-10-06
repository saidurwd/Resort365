<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Restaurant\Actions\CloseTableReservation;
use Modules\Restaurant\Actions\SaveTableReservation;
use Modules\Restaurant\Enums\TableReservationStatus;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Requests\TableReservationRequest;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\TableReservation;
use Modules\Restaurant\Services\OutletAccess;
use Modules\Restaurant\Services\TableReservations;

/**
 * Restaurant → Table reservations (ARCHITECTURE §5.10.12): an outlet's day with its bookings, to book,
 * change, cancel or mark no-show. The host seats parties on the POS floor.
 */
class TableReservationController extends Controller
{
    public function index(Request $request, TableReservations $reservations, OutletAccess $access, FolioPostingContract $folios): View
    {
        $propertyId = app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
        $ids = $access->outletIds((int) $request->user()?->getAuthIdentifier());
        $outlets = Outlet::query()->where('property_id', $propertyId)->where('is_active', true)->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))->orderBy('sort_order')->get();
        $outlet = $outlets->firstWhere('id', $request->integer('outlet')) ?? $outlets->first();
        $timezone = $reservations->timezone($propertyId);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) ? (string) $request->query('date') : now($timezone)->toDateString();

        return view('restaurant::reservations.index', [
            'outlets' => $outlets, 'outlet' => $outlet, 'date' => $date, 'timezone' => $timezone,
            'reservations' => $outlet instanceof Outlet ? $reservations->forDay($outlet, $date) : [],
            'tables' => $outlet instanceof Outlet ? DiningTable::query()->where('outlet_id', $outlet->id)->where('is_active', true)->orderBy('number')->get(['id', 'number', 'seats']) : collect(),
            'stays' => $folios->chargeableStays($propertyId),
            'editing' => $request->filled('edit') ? TableReservation::query()->where('status', TableReservationStatus::Booked->value)->find($request->integer('edit')) : null,
            'canManage' => $request->user()?->can('restaurant.reservation.manage') ?? false,
        ]);
    }

    public function store(TableReservationRequest $request, SaveTableReservation $save): RedirectResponse
    {
        return $this->persist($request, $save, null);
    }

    public function update(TableReservationRequest $request, TableReservation $reservation, SaveTableReservation $save): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        return $this->persist($request, $save, $reservation);
    }

    public function close(Request $request, TableReservation $reservation, CloseTableReservation $close): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        try {
            $close->handle($reservation, TableReservationStatus::tryFrom((string) $request->input('status')) ?? TableReservationStatus::Cancelled);
        } catch (RestaurantSetupInvalid $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Reservation updated.'));
    }

    private function persist(TableReservationRequest $request, SaveTableReservation $save, ?TableReservation $reservation): RedirectResponse
    {
        $outlet = Outlet::query()->findOrFail($request->integer('outlet_id'));
        Gate::authorize('update', $reservation ?? new TableReservation(['outlet_id' => $outlet->id]));

        try {
            $saved = $save->handle($outlet, $reservation, [
                'date' => (string) $request->validated('date'), 'time' => (string) $request->validated('time'), 'party_size' => (int) $request->validated('party_size'),
                'dining_table_id' => $request->filled('dining_table_id') ? (int) $request->validated('dining_table_id') : null,
                'reservation_id' => $request->filled('reservation_id') ? (int) $request->validated('reservation_id') : null,
                'customer_name' => $request->validated('customer_name'), 'phone' => $request->validated('phone'), 'occasion' => $request->validated('occasion'),
                'notes' => $request->validated('notes'), 'duration_minutes' => $request->filled('duration_minutes') ? (int) $request->validated('duration_minutes') : null,
            ], (int) $request->user()?->getAuthIdentifier());
        } catch (RestaurantSetupInvalid $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('restaurant.reservations.index', ['outlet' => $outlet->id, 'date' => (string) $request->validated('date')])
            ->with('success', $reservation instanceof TableReservation ? __('Reservation saved.') : __('Table booked for :name.', ['name' => $saved->customer_name]));
    }
}
