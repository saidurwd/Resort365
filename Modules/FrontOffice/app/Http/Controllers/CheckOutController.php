<?php

namespace Modules\FrontOffice\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Billing\Contracts\FolioSettlement;
use Modules\Billing\DTOs\InvoiceSummary;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\FrontOffice\Actions\CheckOutGuest;
use Modules\FrontOffice\Actions\PostStayCharges;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\CompanySummary;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Exceptions\StayNotPossible;

/**
 * The check-out screen (ARCHITECTURE §5.7): post the stay's room charges, settle each folio (pay in
 * one or more methods, refund a credit, or move the balance to a company's city ledger), return the
 * security deposit, then check out — which invoices and closes the folios.
 */
class CheckOutController extends Controller
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly FolioSettlement $settlement,
    ) {}

    public function show(int $reservation, StayOperations $stays, PropertyDirectory $properties, GuestLookup $guests): View
    {
        Gate::authorize('frontoffice.checkout.perform');
        $summary = $this->summary($reservation);

        return view('frontoffice::check-out.show', [
            'reservation' => $summary,
            'property' => $properties->find($summary->propertyId),
            'unposted' => count($stays->unpostedNights($summary->id)),
            'folios' => $this->settlement->folios($summary->id),
            'settled' => $this->settlement->isSettled($summary->id),
            'companies' => array_map(fn (CompanySummary $company): array => ['id' => $company->id, 'name' => $company->name], $guests->searchCompanies('', 200)),
        ]);
    }

    public function charges(Request $request, int $reservation, PostStayCharges $post): RedirectResponse
    {
        Gate::authorize('frontoffice.checkout.perform');
        $summary = $this->summary($reservation);

        try {
            $count = $post->handle($summary->id, $this->userId($request));
        } catch (ChargeRejected $exception) {
            return to_route('frontoffice.check-out.show', $summary->id)->with('error', $exception->getMessage());
        }

        return to_route('frontoffice.check-out.show', $summary->id)->with('success', trans_choice(':count room night posted.|:count room nights posted.', $count));
    }

    public function store(Request $request, int $reservation, CheckOutGuest $checkOut): RedirectResponse
    {
        Gate::authorize('frontoffice.checkout.perform');
        $summary = $this->summary($reservation);

        try {
            $invoices = $checkOut->handle($summary->id, $this->userId($request));
        } catch (StayNotPossible|ChargeRejected $exception) {
            return to_route('frontoffice.check-out.show', $summary->id)->with('error', $exception->getMessage());
        }

        return redirect()->to(route('reservation.bookings.show', $summary->id).'#folios')->with('success', __(':guest is checked out. Invoices: :invoices.', [
            'guest' => $summary->guestName, 'invoices' => implode(', ', array_map(fn (InvoiceSummary $invoice): string => $invoice->invoiceNo, $invoices)) ?: __('none'),
        ]));
    }

    private function summary(int $reservationId): ReservationSummary
    {
        return $this->reservations->find($reservationId) ?? abort(404);
    }

    private function userId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }
}
