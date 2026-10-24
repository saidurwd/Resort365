<?php

namespace Modules\Website\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Core\Contracts\Settings;
use Modules\Reservation\Contracts\BookingEngine;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Website\Actions\CancelOnlineBooking;
use Modules\Website\Http\Requests\CancelOnlineBookingRequest;
use Modules\Website\Models\WebsiteBooking;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Services\SitePages;

/**
 * A guest's website booking (Step 8.2b) at `/resort/{site}/booking/{code}`, a permanent signed link (shown after booking and sent in
 * the booking emails): its details, what to pay now and how, and cancelling within the policy. The language may be switched without
 * breaking the signature.
 */
class ManageBookingController extends Controller
{
    public function __construct(private readonly SitePages $pages, private readonly ReservationLookup $reservations, private readonly BookingEngine $engine) {}

    public function show(Request $request, string $site, string $code, Settings $settings): View
    {
        [$current, $booking] = $this->booking($request, $code);
        $reservation = $this->reservations->find($booking->reservation_id) ?? abort(404);
        $property = $this->pages->property($current);

        return view('website::public.booking', [
            'booking' => $booking, 'reservation' => $reservation, 'property' => $property, 'currency' => $reservation->currencyCode,
            'hasDining' => $this->pages->dining($current) !== [],
            'cancellation' => $this->engine->cancellationQuote($booking->reservation_id),
            'instructions' => (string) $settings->get('reservation.payment_instructions', $current->property_id),
            'cancelUrl' => URL::signedRoute('website.booking.cancel', ['site' => $current->slug, 'code' => $booking->reservation_code]),
        ]);
    }

    public function cancel(CancelOnlineBookingRequest $request, string $site, string $code, CancelOnlineBooking $cancel): RedirectResponse
    {
        [$current, $booking] = $this->booking($request, $code);
        $back = URL::signedRoute('website.booking.show', ['site' => $current->slug, 'code' => $booking->reservation_code]);

        try {
            $cancel->handle($booking);
        } catch (ReservationNotChangeable $exception) {
            return redirect()->to($back)->with('error', $exception->getMessage());
        }

        return redirect()->to($back)->with('success', __('Your booking is cancelled.'));
    }

    /**
     * @return array{WebsiteSite, WebsiteBooking}
     */
    private function booking(Request $request, string $code): array
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['lang']), 403, __('This link is not valid. Please use the link in your booking email.'));

        $site = $request->attributes->get('website.site');
        abort_unless($site instanceof WebsiteSite, 404);
        $booking = WebsiteBooking::query()->where('website_site_id', $site->id)->where('reservation_code', $code)->first() ?? abort(404);

        return [$site, $booking];
    }
}
