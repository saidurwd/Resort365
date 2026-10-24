<?php

namespace Modules\Website\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Core\Contracts\ReferenceData;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\CancellationPolicySummary;
use Modules\Rates\DTOs\DepositPolicySummary;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Website\Actions\BookOnline;
use Modules\Website\Enums\PayChoice;
use Modules\Website\Exceptions\BookingRefused;
use Modules\Website\Http\Requests\CheckoutRequest;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Services\Cart;
use Modules\Website\Services\CartPricer;
use Modules\Website\Services\PolicyWording;
use Modules\Website\Services\SitePages;

/**
 * The website's checkout (Step 8.2b): the cart priced once more, the guest's details, deposit or full payment, the policies, and the
 * booking (BookOnline). Afterwards the guest lands on their booking's page (a signed link that is also in their email).
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly Cart $cart, private readonly CartPricer $pricer, private readonly SitePages $pages) {}

    public function show(Request $request, RateLookup $rates, ReferenceData $reference): View|RedirectResponse
    {
        $site = $this->site($request);
        $cart = $this->cart->get($site);
        $priced = $this->pricer->price($site, $cart);

        if ($priced['quote'] === null) {
            return to_route('website.book', ['site' => $site->slug])->with('error', $cart['items'] === [] ? __('Your booking is empty. Choose a room or a cottage first.') : __('Something in your booking is no longer available. Please check it.'));
        }

        $property = $this->pages->property($site);
        $currency = $property->currencyCode ?? 'BDT';
        $deposit = $rates->depositPolicy((int) $cart['rate_plan_id']);
        $cancellation = $rates->cancellationPolicy((int) $cart['rate_plan_id']);

        return view('website::public.checkout', [
            'cart' => $cart, 'quote' => $priced['quote'], 'property' => $property, 'currency' => $currency, 'hasDining' => $this->pages->dining($site) !== [],
            'ratePlan' => $rates->ratePlan((int) $cart['rate_plan_id']),
            'depositTerms' => $deposit instanceof DepositPolicySummary ? PolicyWording::deposit($deposit->terms, $currency) : [__('No deposit is needed.')],
            'cancellationTerms' => $cancellation instanceof CancellationPolicySummary ? PolicyWording::cancellation($cancellation->terms, $currency) : [__('Free cancellation.')],
            'countries' => $reference->countries(),
            'choices' => PayChoice::options(),
        ]);
    }

    public function store(CheckoutRequest $request, BookOnline $book): RedirectResponse
    {
        $site = $this->site($request);

        try {
            $booked = $book->handle($site, $this->cart->get($site), $request->guest(), PayChoice::from((string) $request->validated('pay_choice')),
                $request->validated('special_requests') !== null ? (string) $request->validated('special_requests') : null, $request->ip());
        } catch (ValidationException|RoomNoLongerAvailable) {
            return to_route('website.book', ['site' => $site->slug])->with('error', __('Sorry, something in your booking has just been taken. Please check your booking and choose again.'));
        } catch (BookingRefused|BookingNotPossible|DepositBelowMinimum $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to(URL::signedRoute('website.booking.show', ['site' => $site->slug, 'code' => $booked['reservation']->code]))
            ->with('success', __('Thank you! Your booking :code is made.', ['code' => $booked['reservation']->code]));
    }

    private function site(Request $request): WebsiteSite
    {
        $site = $request->attributes->get('website.site');

        return $site instanceof WebsiteSite ? $site : abort(404);
    }
}
