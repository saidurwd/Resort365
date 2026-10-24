<?php

namespace Modules\Website\Http\Controllers\Public;

use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Modules\Billing\Contracts\OnlinePayments;
use Modules\Billing\DTOs\OnlinePaymentRequest;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Enums\OnlinePaymentPurpose;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Core\Contracts\Settings;
use Modules\Reservation\Contracts\BookingEngine;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Exceptions\ReservationNotChangeable;
use Modules\Website\Actions\CancelOnlineBooking;
use Modules\Website\Enums\PayChoice;
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

    public function show(Request $request, string $site, string $code, Settings $settings, OnlinePayments $online): View
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
            // Online payment (Step 8.3): what is still due of what the guest chose to pay, and the property's gateways.
            'dueNow' => self::dueNow($booking, $reservation),
            'options' => $online->options($current->property_id),
            'payUrl' => URL::signedRoute('website.booking.pay', ['site' => $current->slug, 'code' => $booking->reservation_code]),
            'paymentOutcome' => $request->query('payment'),
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
     * Pays online what is still due (Step 8.3): the guest picks a gateway and is sent to it; they come back to this page.
     */
    public function pay(Request $request, string $site, string $code, OnlinePayments $online): RedirectResponse
    {
        [$current, $booking] = $this->booking($request, $code);
        $reservation = $this->reservations->find($booking->reservation_id) ?? abort(404);
        $back = URL::signedRoute('website.booking.show', ['site' => $current->slug, 'code' => $booking->reservation_code]);
        $provider = GatewayProvider::tryFrom((string) $request->input('provider')) ?? abort(422);

        try {
            $url = $online->start(new OnlinePaymentRequest($reservation->id, $provider, self::dueNow($booking, $reservation),
                $booking->pay_choice === PayChoice::Full ? OnlinePaymentPurpose::Full : OnlinePaymentPurpose::Deposit, $back));
        } catch (PaymentNotAllowed $refused) {
            return redirect()->to($back)->with('error', $refused->getMessage());
        }

        return redirect()->away($url);
    }

    /** What is still to pay now of what the guest chose (the deposit or everything). */
    public static function dueNow(WebsiteBooking $booking, ReservationSummary $reservation): string
    {
        $chosen = BigDecimal::min(BigDecimal::of($booking->amount_due_now), BigDecimal::of($reservation->grandTotal));

        return (string) BigDecimal::max($chosen->minus($reservation->amountPaid), BigDecimal::zero())->toScale(2);
    }

    /**
     * @return array{WebsiteSite, WebsiteBooking}
     */
    private function booking(Request $request, string $code): array
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['lang', 'payment']), 403, __('This link is not valid. Please use the link in your booking email.'));

        $site = $request->attributes->get('website.site');
        abort_unless($site instanceof WebsiteSite, 404);
        $booking = WebsiteBooking::query()->where('website_site_id', $site->id)->where('reservation_code', $code)->first() ?? abort(404);

        return [$site, $booking];
    }
}
