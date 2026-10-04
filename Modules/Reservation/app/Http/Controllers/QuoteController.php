<?php

namespace Modules\Reservation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Actions\ConvertQuote;
use Modules\Reservation\Actions\DeclineQuote;
use Modules\Reservation\Actions\SendQuote;
use Modules\Reservation\Enums\QuoteStatus;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\QuoteNotOpen;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Services\BookingDocuments;
use Modules\Reservation\Services\QuotesTable;

/**
 * Quotes: the list, a quote's page, its PDF, and emailing, declining or booking it.
 */
class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Quote::class);

        return view('reservation::quotes.index', [
            'columns' => QuotesTable::columns(),
            'status' => QuoteStatus::tryFrom((string) $request->query('status', '')),
            'statuses' => QuoteStatus::cases(),
        ]);
    }

    public function data(Request $request, QuotesTable $table): JsonResponse
    {
        Gate::authorize('viewAny', Quote::class);
        $search = $request->input('search.value');

        return $table->toJson(QuoteStatus::tryFrom((string) $request->query('status', '')), is_string($search) ? $search : '');
    }

    public function show(Quote $quote, GuestLookup $guests, RateLookup $rates, PropertyDirectory $properties): View
    {
        Gate::authorize('view', $quote);
        $quote->load('items.nights');

        return view('reservation::quotes.show', [
            'quote' => $quote,
            'status' => $quote->currentStatus(),
            'guest' => $guests->find($quote->guest_id),
            'plan' => $rates->ratePlan($quote->rate_plan_id),
            'timezone' => $properties->find($quote->property_id)->timezone ?? 'UTC',
        ]);
    }

    public function pdf(Quote $quote, BookingDocuments $documents): Response
    {
        Gate::authorize('view', $quote);

        return response($documents->quote($quote), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$quote->code.'.pdf"',
        ]);
    }

    public function send(Quote $quote, SendQuote $send): RedirectResponse
    {
        Gate::authorize('update', $quote);

        try {
            $send->handle($quote);
        } catch (QuoteNotOpen|BookingNotPossible $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Quote :code is being emailed to the guest.', ['code' => $quote->code]));
    }

    public function convert(Request $request, Quote $quote, ConvertQuote $convert): RedirectResponse
    {
        Gate::authorize('update', $quote);
        Gate::authorize('create', Reservation::class);

        try {
            $reservation = $convert->handle($quote, $request->user() !== null ? (int) $request->user()->getAuthIdentifier() : null);
        } catch (QuoteNotOpen|BookingNotPossible|RoomNoLongerAvailable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->to(route('reservation.bookings.show', $reservation).'#payments')
            ->with('success', __('Quote :quote booked as :code at the quoted prices.', ['quote' => $quote->code, 'code' => $reservation->code]));
    }

    public function decline(Quote $quote, DeclineQuote $decline): RedirectResponse
    {
        Gate::authorize('update', $quote);

        try {
            $decline->handle($quote);
        } catch (QuoteNotOpen $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', __('Quote :code marked as declined.', ['code' => $quote->code]));
    }
}
