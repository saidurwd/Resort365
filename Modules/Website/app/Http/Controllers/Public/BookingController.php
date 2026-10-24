<?php

namespace Modules\Website\Http\Controllers\Public;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Website\Http\Requests\AddToCartRequest;
use Modules\Website\Http\Requests\SearchStayRequest;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Services\Cart;
use Modules\Website\Services\CartPricer;
use Modules\Website\Services\SitePages;
use Modules\Website\Services\SiteSearch;

/**
 * The website's search and cart (Step 8.2b): `/resort/{site}/book` shows what is free for the dates and party, per rate plan sold
 * online, beside the guest's cart; rooms and whole cottages are added to and removed from the cart here.
 */
class BookingController extends Controller
{
    public function __construct(private readonly Cart $cart, private readonly CartPricer $pricer, private readonly SitePages $pages) {}

    public function search(SearchStayRequest $request, SiteSearch $search): View
    {
        $site = $this->site($request);
        $cart = $this->cart->get($site);
        $stay = $request->filled('check_in')
            ? ['check_in' => (string) $request->input('check_in'), 'check_out' => (string) $request->input('check_out'), 'adults' => $request->integer('adults', 2), 'children' => $request->integer('children'), 'promo_code' => trim((string) $request->input('promo_code')) ?: null]
            : null;
        $results = $stay === null ? [] : $search->search($site, CarbonImmutable::parse($stay['check_in']), CarbonImmutable::parse($stay['check_out']), $stay['adults'], $stay['children'], $stay['promo_code']);

        return $this->page('book', $site, [
            'stay' => $stay ?? ['check_in' => $cart['check_in'], 'check_out' => $cart['check_out'], 'adults' => 2, 'children' => 0, 'promo_code' => $cart['promo_code']],
            'searched' => $stay !== null,
            'results' => $results,
            'cart' => $cart,
            'priced' => $this->pricer->price($site, $cart),
        ]);
    }

    public function add(AddToCartRequest $request): RedirectResponse
    {
        $site = $this->site($request);
        $refused = $this->cart->add($site, (string) $request->input('check_in'), (string) $request->input('check_out'), $request->integer('rate_plan_id'),
            $request->input('promo_code') !== null ? (string) $request->input('promo_code') : null, (string) $request->input('type'), $request->integer('id'),
            $request->integer('adults'), $request->integer('children'));

        $back = route('website.book', ['site' => $site->slug, ...$request->only(['check_in', 'check_out', 'adults', 'children', 'promo_code'])]).'#cart';

        return $refused !== null ? redirect($back)->with('error', $refused) : redirect($back)->with('success', __('Added to your booking.'));
    }

    public function remove(Request $request, string $site, int $index): RedirectResponse
    {
        $current = $this->site($request);
        $this->cart->remove($current, $index);

        return redirect()->to(url()->previous(route('website.book', ['site' => $current->slug])))->with('success', __('Removed from your booking.'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(string $view, WebsiteSite $site, array $data): View
    {
        $property = $this->pages->property($site);

        return view('website::public.'.$view, [...$data, 'property' => $property, 'currency' => $property->currencyCode ?? 'BDT', 'hasDining' => $this->pages->dining($site) !== [],
            'units' => collect($this->pages->units($site))->keyBy('key')->all()]);
    }

    private function site(Request $request): WebsiteSite
    {
        $site = $request->attributes->get('website.site');

        return $site instanceof WebsiteSite ? $site : abort(404);
    }
}
