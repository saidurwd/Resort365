<?php

namespace Modules\Website\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Modules\Reservation\Contracts\BookingEngine;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Website\Models\WebsiteSite;

/**
 * Prices a website cart (Step 8.2b) through Reservation's BookingEngine, sold through the `online` channel: each item is checked
 * against availability for its party (a room type takes its first free room), then the stay is priced with its deposit. Items that
 * cannot be booked any more come back with the reason, and no price.
 *
 * @phpstan-import-type CartState from Cart
 */
class CartPricer
{
    public function __construct(private readonly BookingEngine $engine) {}

    /**
     * @param  CartState  $cart
     * @return array{quote: BookingQuote|null, items: list<BookingItem>, problems: array<int, string>}
     */
    public function price(WebsiteSite $site, array $cart): array
    {
        if ($cart['items'] === [] || $cart['check_in'] === null || $cart['check_out'] === null || $cart['rate_plan_id'] === null) {
            return ['quote' => null, 'items' => [], 'problems' => []];
        }

        try {
            $items = $this->items($site, $cart);
            $quote = $this->engine->quote($this->reservation($site, $cart, $items, 0));

            return ['quote' => $quote, 'items' => $items, 'problems' => []];
        } catch (ValidationException $exception) {
            $problems = [];

            foreach ($exception->errors() as $key => $messages) {
                $problems[preg_match('/^items\.(\d+)$/', (string) $key, $match) === 1 ? (int) $match[1] : -1] = (string) ($messages[0] ?? '');
            }

            return ['quote' => null, 'items' => [], 'problems' => $problems];
        } catch (BookingNotPossible $exception) {
            return ['quote' => null, 'items' => [], 'problems' => [-1 => $exception->getMessage()]];
        }
    }

    /**
     * The cart's stay as a booking for the guest.
     *
     * @param  CartState  $cart
     * @param  list<BookingItem>  $items
     */
    public function reservation(WebsiteSite $site, array $cart, array $items, int $guestId, ?string $specialRequests = null): NewReservation
    {
        return new NewReservation(
            $site->property_id, CarbonImmutable::parse((string) $cart['check_in']), CarbonImmutable::parse((string) $cart['check_out']), $items, $guestId,
            ReservationSource::Website, promoCode: $cart['promo_code'], specialRequests: $specialRequests,
        );
    }

    /**
     * @param  CartState  $cart
     * @return list<BookingItem>
     *
     * @throws ValidationException
     */
    public function items(WebsiteSite $site, array $cart): array
    {
        $rows = array_map(fn (array $item): array => [
            'type' => $item['type'],
            'room_type_id' => $item['type'] === 'room' ? $item['id'] : null,
            'cottage_id' => $item['type'] === 'cottage' ? $item['id'] : null,
            'adults' => $item['adults'],
            'children' => $item['children'],
        ], $cart['items']);

        return $this->engine->resolveItems($site->property_id, (int) $cart['rate_plan_id'], CarbonImmutable::parse((string) $cart['check_in']), CarbonImmutable::parse((string) $cart['check_out']), $rows, $cart['promo_code'], 'online');
    }
}
