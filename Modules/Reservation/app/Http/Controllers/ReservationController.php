<?php

namespace Modules\Reservation\Http\Controllers;

use App\Support\Tenancy\ModuleAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Guest\Contracts\GuestLookup;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Contracts\ReservationTabs;
use Modules\Reservation\DTOs\ReservationTab;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Http\Requests\ReservationListRequest;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Services\ItemLabels;
use Modules\Reservation\Services\ReservationBalance;
use Modules\Reservation\Services\ReservationsTable;

/**
 * The reservation list of the current property, and a reservation's page (tabs: Summary, Rooms,
 * Guests, other modules' tabs such as Payments, History).
 */
class ReservationController extends Controller
{
    public function index(ReservationListRequest $request): View
    {
        Gate::authorize('viewAny', Reservation::class);

        return view('reservation::bookings.index', [
            'columns' => ReservationsTable::columns(),
            'filters' => $request->filters(),
            'statuses' => ReservationStatus::cases(),
            'sources' => ReservationSource::cases(),
        ]);
    }

    public function data(ReservationListRequest $request, ReservationsTable $table): JsonResponse
    {
        Gate::authorize('viewAny', Reservation::class);
        $search = $request->input('search.value');

        return $table->toJson($request->filters(), is_string($search) ? $search : '');
    }

    public function show(Request $request, Reservation $reservation, GuestLookup $guests, ItemLabels $labels, PropertyDirectory $properties,
        RateLookup $rates, ReservationTabs $tabs, ModuleAccess $modules, AuditTrail $audit, UserDirectory $users, ReservationBalance $balance): View
    {
        Gate::authorize('view', $reservation);
        $reservation->load(['items.nights', 'guests', 'logs']);

        $userNames = [];

        foreach ($users->all() as $user) {
            $userNames[$user->id] = $user->name;
        }

        return view('reservation::bookings.show', [
            'reservation' => $reservation,
            'guest' => $guests->find($reservation->primary_guest_id),
            'guests' => $reservation->guests->sortByDesc('is_primary')->map(fn (ReservationGuest $row): array => ['row' => $row, 'guest' => $guests->find($row->guest_id)])->values(),
            'labels' => $labels->forProperty($reservation->property_id),
            'plan' => $rates->ratePlan($reservation->rate_plan_id),
            'depositPolicy' => $rates->depositPolicy($reservation->rate_plan_id),
            'timezone' => $properties->find($reservation->property_id)->timezone ?? 'UTC',
            'tabs' => collect($tabs->all())
                ->filter(fn (ReservationTab $tab): bool => $modules->enabled($tab->module) && ($request->user()?->can($tab->permission) ?? false))
                ->map(fn (ReservationTab $tab): array => ['tab' => $tab, 'content' => ($tab->render)($reservation->id)])
                ->values(),
            'history' => $audit->for($reservation),
            'userNames' => $userNames,
            // What was paid above the cancellation fee (refunded in Step 2.6).
            'refundDue' => $balance->balance($reservation->amount_paid, $reservation->cancellation_fee ?? '0'),
        ]);
    }
}
