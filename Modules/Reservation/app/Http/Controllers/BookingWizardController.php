<?php

namespace Modules\Reservation\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Contracts\GuestRegistry;
use Modules\Guest\DTOs\GuestDetails;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\RatePlanSummary;
use Modules\Reservation\Actions\CreateReservation;
use Modules\Reservation\Actions\SaveQuote;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Reservation\Http\Requests\BookingChoiceRequest;
use Modules\Reservation\Http\Requests\BookingDatesRequest;
use Modules\Reservation\Http\Requests\BookingGuestRequest;
use Modules\Reservation\Http\Requests\BookingPricingRequest;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\BookingQuoter;
use Modules\Reservation\Support\BookingWizard;

/**
 * The 5-step booking wizard (ARCHITECTURE §10.3): dates & guests → choose cottages and rooms →
 * guest → pricing & deposit → confirm. Progress lives in the session (BookingWizard); every price
 * is worked out again on the server (BookingQuoter / CreateReservation). Authorized by the
 * reservation.booking.create route middleware and the step requests.
 */
class BookingWizardController extends Controller
{
    public function __construct(
        private readonly BookingWizard $wizard,
        private readonly RateLookup $rates,
        private readonly PropertyDirectory $properties,
    ) {}

    public function dates(Request $request): View
    {
        $propertyId = $this->propertyId();
        $businessDate = CarbonImmutable::parse($this->properties->find($propertyId)->businessDate ?? now()->toDateString());

        // ?walk_in=1 (front desk): a fresh booking for tonight, sourced as a walk-in.
        if ($request->boolean('walk_in')) {
            $this->wizard->clear();
            $this->wizard->put($propertyId, ['source' => ReservationSource::WalkIn->value]);

            return $this->step('dates', 1, [
                'plans' => $this->frontDeskPlans($propertyId),
                'values' => ['check_in' => $businessDate->toDateString(), 'check_out' => $businessDate->addDay()->toDateString(), 'adults' => 2, 'children' => 0],
            ]);
        }

        $state = $this->wizard->state($propertyId);

        return $this->step('dates', 1, [
            'plans' => $this->frontDeskPlans($propertyId),
            'values' => $state + ['check_in' => $businessDate->toDateString(), 'check_out' => $businessDate->addDays(2)->toDateString(), 'adults' => 2, 'children' => 0],
        ]);
    }

    public function storeDates(BookingDatesRequest $request): RedirectResponse
    {
        $propertyId = $request->propertyId();
        $this->wizard->forget($propertyId, ['cottages', 'rooms', 'occupancy', 'priced']);
        $this->wizard->put($propertyId, $request->validated());

        return to_route('reservation.bookings.choose');
    }

    public function choose(): View|RedirectResponse
    {
        $propertyId = $this->propertyId();

        if ($this->wizard->nextStep($propertyId) < 2) {
            return to_route('reservation.bookings.create');
        }

        $state = $this->wizard->state($propertyId);

        return $this->step('choose', 2, [
            'result' => $this->wizard->availability($propertyId),
            'chosenCottages' => array_map(intval(...), (array) ($state['cottages'] ?? [])),
            'chosenRooms' => array_map(intval(...), (array) ($state['rooms'] ?? [])),
        ]);
    }

    public function storeChoice(BookingChoiceRequest $request): RedirectResponse
    {
        $propertyId = $request->propertyId();
        $this->wizard->forget($propertyId, ['occupancy', 'priced']);
        $this->wizard->put($propertyId, [
            'cottages' => array_map(intval(...), (array) $request->validated('cottages', [])),
            'rooms' => array_map(intval(...), (array) $request->validated('rooms', [])),
        ]);

        return to_route('reservation.bookings.guest');
    }

    public function guest(GuestLookup $guests): View|RedirectResponse
    {
        $propertyId = $this->propertyId();

        if ($this->wizard->nextStep($propertyId) < 3) {
            return to_route('reservation.bookings.choose');
        }

        $state = $this->wizard->state($propertyId);

        return $this->step('guest', 3, [
            'state' => $state,
            'chosenGuest' => isset($state['guest_id']) ? $guests->find((int) $state['guest_id']) : null,
            'companies' => collect($guests->searchCompanies('', 200))->mapWithKeys(fn ($company): array => [$company->id => $company->name])->all(),
            'travelAgents' => collect($guests->searchTravelAgents('', 200))->mapWithKeys(fn ($agent): array => [$agent->id => $agent->name])->all(),
        ]);
    }

    public function storeGuest(BookingGuestRequest $request, GuestLookup $guests): RedirectResponse
    {
        $propertyId = $this->propertyId();
        $data = $request->validated();
        $common = ['company_id' => $data['company_id'] ?? null, 'travel_agent_id' => $data['travel_agent_id'] ?? null, 'source' => $data['source']];

        if ($data['guest_mode'] === 'existing') {
            $this->wizard->forget($propertyId, ['new_guest']);
            $this->wizard->put($propertyId, ['guest_id' => (int) $data['guest_id'], ...$common]);

            return to_route('reservation.bookings.pricing');
        }

        $duplicates = $guests->findDuplicates($data['phone'] ?? null, $data['email'] ?? null);

        if ($duplicates !== [] && ! $request->boolean('confirm_new')) {
            return back()->withInput()->with('guest_duplicates', array_map(fn (GuestSummary $guest): array => $guest->toArray(), $duplicates));
        }

        $this->wizard->forget($propertyId, ['guest_id']);
        $this->wizard->put($propertyId, ['new_guest' => [
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'] ?? null, 'phone' => $data['phone'] ?? null, 'email' => $data['email'] ?? null,
        ], ...$common]);

        return to_route('reservation.bookings.pricing');
    }

    public function pricing(BookingQuoter $quoter, InventoryCatalog $catalog): View|RedirectResponse
    {
        $propertyId = $this->propertyId();

        if ($this->wizard->nextStep($propertyId) < 4) {
            return to_route('reservation.bookings.guest');
        }

        [$quote, $error] = $this->quote($quoter, $propertyId);

        return $this->step('pricing', 4, [
            'state' => $this->wizard->state($propertyId),
            'items' => $this->wizard->items($propertyId),
            'labels' => $this->labels($catalog, $propertyId),
            'quote' => $quote,
            'quoteError' => $error,
        ]);
    }

    public function storePricing(BookingPricingRequest $request, BookingQuoter $quoter): RedirectResponse
    {
        $propertyId = $this->propertyId();
        $data = $request->validated();
        $this->wizard->forget($propertyId, ['priced']);
        $this->wizard->put($propertyId, [
            'occupancy' => (array) ($data['occupancy'] ?? []),
            'promo_code' => $data['promo_code'] ?? null,
            'deposit_percent' => $data['deposit_percent'] ?? null,
            'special_requests' => $data['special_requests'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            'group_name' => $data['group_name'] ?? null,
        ]);

        if ($data['action'] === 'recalculate') {
            return to_route('reservation.bookings.pricing');
        }

        [$quote, $error] = $this->quote($quoter, $propertyId);

        if (! $quote instanceof BookingQuote) {
            return to_route('reservation.bookings.pricing')->with('error', $error);
        }

        if (! $quote->depositWithinLimits && ! $request->user()?->can('reservation.deposit.override')) {
            return to_route('reservation.bookings.pricing')->withErrors(['deposit_percent' => __('This deposit is outside the policy. A manager can allow it.')]);
        }

        $this->wizard->put($propertyId, ['priced' => true]);

        return to_route('reservation.bookings.confirm');
    }

    public function confirm(BookingQuoter $quoter, InventoryCatalog $catalog, GuestLookup $guests): View|RedirectResponse
    {
        $propertyId = $this->propertyId();

        if ($this->wizard->nextStep($propertyId) < 5) {
            return to_route('reservation.bookings.pricing');
        }

        [$quote, $error] = $this->quote($quoter, $propertyId);
        $state = $this->wizard->state($propertyId);

        return $this->step('confirm', 5, [
            'state' => $state,
            'quote' => $quote,
            'quoteError' => $error,
            'labels' => $this->labels($catalog, $propertyId),
            'guest' => isset($state['guest_id']) ? $guests->find((int) $state['guest_id']) : null,
            'blacklisted' => isset($state['guest_id']) && $guests->isBlacklisted((int) $state['guest_id']),
            'timezone' => $this->properties->find($propertyId)->timezone ?? 'UTC',
        ]);
    }

    public function store(Request $request, CreateReservation $create, GuestRegistry $registry): RedirectResponse
    {
        Gate::authorize('create', Reservation::class);
        $propertyId = $this->propertyId();

        if ($this->wizard->nextStep($propertyId) < 5) {
            return to_route('reservation.bookings.pricing');
        }

        $guestId = $this->guestId($propertyId, $registry);
        $user = $request->user();

        try {
            $reservation = $create->handle($this->wizard->reservation($propertyId, $guestId, $user !== null ? (int) $user->getAuthIdentifier() : null,
                $user?->can('reservation.deposit.override') ?? false));
        } catch (RoomNoLongerAvailable $exception) {
            $this->wizard->forget($propertyId, ['cottages', 'rooms', 'occupancy', 'priced']);

            return to_route('reservation.bookings.choose')->with('error', $exception->getMessage().' '.__('Please choose again.'));
        } catch (BookingNotPossible|DepositBelowMinimum $exception) {
            $this->wizard->forget($propertyId, ['priced']);

            return to_route('reservation.bookings.pricing')->with('error', $exception->getMessage());
        }

        $this->wizard->clear();

        // A booking waiting for its deposit opens on the Payments tab (Billing), to take it now.
        $url = route('reservation.bookings.show', $reservation).($reservation->status === ReservationStatus::Tentative ? '#payments' : '');

        return redirect()->to($url)->with('success', __('Booking :code created.', ['code' => $reservation->code]));
    }

    /**
     * Save the priced booking as a quote instead (no rooms are held).
     */
    public function storeQuote(Request $request, SaveQuote $save, GuestRegistry $registry): RedirectResponse
    {
        Gate::authorize('create', Quote::class);
        $propertyId = $this->propertyId();

        if ($this->wizard->nextStep($propertyId) < 5) {
            return to_route('reservation.bookings.pricing');
        }

        $guestId = $this->guestId($propertyId, $registry);
        $user = $request->user();

        try {
            $quote = $save->handle($this->wizard->reservation($propertyId, $guestId, $user !== null ? (int) $user->getAuthIdentifier() : null,
                $user?->can('reservation.deposit.override') ?? false));
        } catch (BookingNotPossible|DepositBelowMinimum $exception) {
            $this->wizard->forget($propertyId, ['priced']);

            return to_route('reservation.bookings.pricing')->with('error', $exception->getMessage());
        }

        $this->wizard->clear();

        return to_route('reservation.quotes.show', $quote)->with('success', __('Quote :code saved.', ['code' => $quote->code]));
    }

    public function reset(): RedirectResponse
    {
        $this->wizard->clear();

        return to_route('reservation.bookings.create');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function step(string $view, int $step, array $data): View
    {
        $propertyId = $this->propertyId();

        return view('reservation::bookings.'.$view, [
            ...$data,
            'step' => $step,
            'reached' => $this->wizard->nextStep($propertyId),
            'propertyName' => (string) app(PropertyContext::class)->currentName(),
            'summary' => $this->wizard->state($propertyId),
        ]);
    }

    /**
     * @return array{BookingQuote|null, string|null}
     */
    private function quote(BookingQuoter $quoter, int $propertyId): array
    {
        try {
            return [$quoter->quote($this->wizard->reservation($propertyId)), null];
        } catch (BookingNotPossible $exception) {
            return [null, $exception->getMessage()];
        }
    }

    /**
     * Display names of the chosen items by key ("cottage:3", "room:12").
     *
     * @return array<string, string>
     */
    private function labels(InventoryCatalog $catalog, int $propertyId): array
    {
        $labels = [];

        foreach ($catalog->cottages($propertyId) as $cottage) {
            $labels['cottage:'.$cottage->id] = __(':name (whole cottage)', ['name' => $cottage->name]);
        }

        foreach ($catalog->rooms($propertyId) as $room) {
            $labels['room:'.$room->id] = __('Room :number', ['number' => $room->number]);
        }

        return $labels;
    }

    private function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    /**
     * The chosen guest, or the new guest of step 3 (registered now, once).
     */
    private function guestId(int $propertyId, GuestRegistry $registry): int
    {
        $state = $this->wizard->state($propertyId);
        $guestId = isset($state['guest_id']) ? (int) $state['guest_id'] : $registry->register(new GuestDetails(
            (string) $state['new_guest']['first_name'], $state['new_guest']['last_name'] ?? null, $state['new_guest']['phone'] ?? null,
            $state['new_guest']['email'] ?? null, companyId: isset($state['company_id']) ? (int) $state['company_id'] : null,
        ))->id;
        $this->wizard->forget($propertyId, ['new_guest']);
        $this->wizard->put($propertyId, ['guest_id' => $guestId]);

        return $guestId;
    }

    /**
     * @return array<int, string>
     */
    private function frontDeskPlans(int $propertyId): array
    {
        return collect($this->rates->ratePlans($propertyId))->filter(fn (RatePlanSummary $plan): bool => $plan->sellsThrough('front_desk'))
            ->mapWithKeys(fn (RatePlanSummary $plan): array => [$plan->id => $plan->name])->all();
    }
}
