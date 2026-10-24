<?php

namespace Modules\Website\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\OnlinePayments;
use Modules\Core\Contracts\Settings;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Contracts\GuestRegistry;
use Modules\Guest\DTOs\GuestDetails;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Reservation\Contracts\BookingEngine;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;
use Modules\Website\Enums\PayChoice;
use Modules\Website\Exceptions\BookingRefused;
use Modules\Website\Models\WebsiteBooking;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Services\Cart;
use Modules\Website\Services\CartPricer;

/**
 * Books a website cart for a guest (Step 8.2b): checks every item again, finds the guest (the profile with this email and phone, else
 * a new one), books through Reservation's BookingEngine (source Website, Tentative until the deposit is paid; its locks hold the
 * rooms), records what the guest chose to pay now, and empties the cart. At most three open website bookings per email or address a
 * day; a blacklisted guest is refused politely.
 *
 * @phpstan-import-type CartState from Cart
 */
class BookOnline extends Action
{
    public const int OPEN_BOOKINGS_PER_DAY = 3;

    public function __construct(
        private readonly CartPricer $pricer,
        private readonly Cart $cart,
        private readonly BookingEngine $engine,
        private readonly GuestLookup $guests,
        private readonly GuestRegistry $registry,
        private readonly ReservationLookup $reservations,
        private readonly OnlinePayments $online,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  CartState  $cart
     * @return array{reservation: ReservationSummary, booking: WebsiteBooking}
     *
     * @throws ValidationException|BookingRefused|BookingNotPossible|DepositBelowMinimum|RoomNoLongerAvailable
     */
    public function handle(WebsiteSite $site, array $cart, GuestDetails $guest, PayChoice $choice, ?string $specialRequests, ?string $ip): array
    {
        if ($this->openBookings((string) $guest->email, $ip) >= self::OPEN_BOOKINGS_PER_DAY) {
            throw new BookingRefused(__('We could not take this booking online. Please call or email us and we will gladly help.'));
        }

        $items = $this->pricer->items($site, $cart);
        $match = $this->guests->matchContact((string) $guest->email, (string) $guest->phone);

        if ($match instanceof GuestSummary && $match->isBlacklisted) {
            throw new BookingRefused(__('We could not take this booking online. Please call or email us and we will gladly help.'));
        }

        $guestId = $match->id ?? $this->registry->register($guest)->id;
        // With online payment (Step 8.3) the deposit is due within the payment window, so unpaid rooms are released quickly.
        $window = $this->online->options($site->property_id) !== [] ? (int) $this->settings->get('website.payment_minutes', $site->property_id) : null;
        $reservation = $this->engine->book($this->pricer->reservation($site, $cart, $items, $guestId, $specialRequests, $window));

        $dueNow = $choice === PayChoice::Full || BigDecimal::of($reservation->depositRequired)->isZero() ? $reservation->grandTotal : $reservation->depositRequired;
        $booking = WebsiteBooking::query()->create([
            'property_id' => $site->property_id, 'website_site_id' => $site->id, 'reservation_id' => $reservation->id, 'reservation_code' => $reservation->code,
            'pay_choice' => $choice, 'amount_due_now' => $dueNow,
            'currency_code' => $reservation->currencyCode, 'email' => strtolower((string) $guest->email), 'ip_address' => $ip,
        ]);

        $this->cart->clear($site);

        return ['reservation' => $reservation, 'booking' => $booking];
    }

    /**
     * Website bookings of the last day from this email or address that are still waiting for their deposit.
     */
    private function openBookings(string $email, ?string $ip): int
    {
        $recent = WebsiteBooking::query()->where('created_at', '>=', now()->subDay())
            ->where(fn ($query) => $query->where('email', strtolower($email))->when($ip !== null, fn ($query) => $query->orWhere('ip_address', $ip)))
            ->pluck('reservation_id');

        return $recent->filter(fn (int $id): bool => $this->reservations->find($id)?->status === ReservationStatus::Tentative)->count();
    }
}
