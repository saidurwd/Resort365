<?php

namespace Modules\Billing\Http\Controllers\Online;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Billing\Actions\SendPaymentLink;
use Modules\Billing\Contracts\OnlinePayments;
use Modules\Billing\DTOs\OnlinePaymentRequest;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Enums\OnlinePaymentPurpose;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;

/**
 * A guest's payment link (Step 8.3): `/pay/link/{reservation}/{deposit|balance}`, signed for a week. The page shows what is owed now
 * and the property's gateways; paying starts the online payment and comes back here with `?payment=`.
 */
class PaymentLinkController extends Controller
{
    public function __construct(private readonly ReservationLookup $reservations, private readonly OnlinePayments $online) {}

    public function show(Request $request, int $reservation, string $purpose, PropertyDirectory $properties): View
    {
        [$summary, $kind] = $this->link($request, $reservation, $purpose);

        return view('billing::online.pay-link', [
            'reservation' => $summary, 'purpose' => $kind, 'amount' => (string) SendPaymentLink::amountDue($summary, $kind),
            'options' => $this->online->options($summary->propertyId), 'property' => $properties->find($summary->propertyId),
            'payment' => $request->query('payment'), 'action' => $request->fullUrlWithoutQuery(['payment']),
        ]);
    }

    public function start(Request $request, int $reservation, string $purpose): RedirectResponse
    {
        [$summary, $kind] = $this->link($request, $reservation, $purpose);
        $provider = GatewayProvider::tryFrom((string) $request->input('provider')) ?? abort(422);

        try {
            $url = $this->online->start(new OnlinePaymentRequest($summary->id, $provider, (string) SendPaymentLink::amountDue($summary, $kind), $kind, $request->fullUrlWithoutQuery(['payment'])));
        } catch (PaymentNotAllowed $refused) {
            return back()->with('error', $refused->getMessage());
        }

        return redirect()->away($url);
    }

    /**
     * @return array{0: ReservationSummary, 1: OnlinePaymentPurpose}
     */
    private function link(Request $request, int $reservation, string $purpose): array
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['payment']), 403, __('This payment link is not valid or has expired. Please ask the resort for a new one.'));
        $kind = OnlinePaymentPurpose::tryFrom($purpose) ?? abort(404);
        $summary = $this->reservations->find($reservation) ?? abort(404);

        return [$summary, $kind];
    }
}
