<?php

namespace Modules\Website\Services;

use Illuminate\Contracts\Session\Session;
use Modules\Website\Models\WebsiteSite;

/**
 * A guest's cart on a property's website (Step 8.2b), kept in the session per site: one stay (dates, rate plan, promotion code) and
 * the rooms and whole cottages with their party. Prices are never kept here; CartPricer works them out on every view.
 *
 * @phpstan-type CartItem array{type: string, id: int, adults: int, children: int}
 * @phpstan-type CartState array{check_in: string|null, check_out: string|null, rate_plan_id: int|null, promo_code: string|null, items: list<CartItem>}
 */
class Cart
{
    public const int MAX_ITEMS = 10;

    public function __construct(private readonly Session $session) {}

    /**
     * @return CartState
     */
    public function get(WebsiteSite $site): array
    {
        $state = $this->session->get($this->key($site));

        return is_array($state) ? $state + ['check_in' => null, 'check_out' => null, 'rate_plan_id' => null, 'promo_code' => null, 'items' => []]
            : ['check_in' => null, 'check_out' => null, 'rate_plan_id' => null, 'promo_code' => null, 'items' => []];
    }

    public function isEmpty(WebsiteSite $site): bool
    {
        return $this->get($site)['items'] === [];
    }

    /**
     * Adds a room type (`room`, room type id) or a whole cottage (`cottage`, cottage id) for the stay. New dates move the whole cart
     * to them (everything is checked again when it is priced); a cart holds one rate plan.
     *
     * @return string|null why it was not added
     */
    public function add(WebsiteSite $site, string $checkIn, string $checkOut, int $ratePlanId, ?string $promoCode, string $type, int $id, int $adults, int $children): ?string
    {
        $cart = $this->get($site);

        if ($cart['items'] !== [] && $cart['rate_plan_id'] !== $ratePlanId) {
            return __('Your cart already has another rate plan. Book it first, or empty the cart to choose this one.');
        }

        if (count($cart['items']) >= self::MAX_ITEMS) {
            return __('A booking can have at most :count rooms or cottages.', ['count' => self::MAX_ITEMS]);
        }

        if ($type === 'cottage' && in_array(['type' => 'cottage', 'id' => $id], array_map(fn (array $item): array => ['type' => $item['type'], 'id' => $item['id']], $cart['items']), true)) {
            return __('This cottage is already in your cart.');
        }

        $cart['items'][] = ['type' => $type, 'id' => $id, 'adults' => $adults, 'children' => $children];
        $this->put($site, [...$cart, 'check_in' => $checkIn, 'check_out' => $checkOut, 'rate_plan_id' => $ratePlanId, 'promo_code' => $promoCode !== null && trim($promoCode) !== '' ? strtoupper(trim($promoCode)) : $cart['promo_code']]);

        return null;
    }

    public function remove(WebsiteSite $site, int $index): void
    {
        $cart = $this->get($site);
        unset($cart['items'][$index]);
        $cart['items'] = array_values($cart['items']);
        $this->put($site, $cart);
    }

    public function clear(WebsiteSite $site): void
    {
        $this->session->forget($this->key($site));
    }

    /**
     * @param  CartState  $cart
     */
    private function put(WebsiteSite $site, array $cart): void
    {
        $this->session->put($this->key($site), $cart);
    }

    private function key(WebsiteSite $site): string
    {
        return 'website.cart.'.$site->id;
    }
}
